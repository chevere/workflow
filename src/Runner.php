<?php

/*
 * This file is part of Chevere.
 *
 * (c) Rodolfo Berrios <rodolfo@chevere.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Chevere\Workflow;

use Amp\Parallel\Worker\Execution;
use Chevere\Parameter\Interfaces\BoolParameterInterface;
use Chevere\Parameter\Interfaces\CastInterface;
use Chevere\Workflow\Conjunctions\NorConjunction;
use Chevere\Workflow\Exceptions\RunnerException;
use Chevere\Workflow\Interfaces\ConjunctionInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\RunnerInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use InvalidArgumentException;
use OutOfBoundsException;
use Throwable;
use function Amp\Future\await;
use function Amp\Parallel\Worker\submit;
use function Chevere\Message\message;
use function Chevere\Parameter\cast;

final class Runner implements RunnerInterface
{
    public function __construct(
        private RunInterface $run,
    ) {
    }

    public function run(): RunInterface
    {
        return $this->run;
    }

    public function withRun(): RunnerInterface
    {
        $new = clone $this;
        $jobs = $new->run->workflow()->jobs();
        $graph = $jobs->graph()->toArray();
        foreach ($graph as $node) {
            if (count($node) === 1) {
                $runner = runnerForJob($new, $node[0]);
                $new->merge($new, $runner);

                continue;
            }
            $executions = $new->getExecutions($node);
            /** @var RunnerInterface[] $responses */
            $responses = await(
                array_map(
                    fn (Execution $e) => $e->getFuture(),
                    $executions,
                )
            );
            foreach ($responses as $runner) {
                $new->merge($new, $runner);
            }
        }

        return $new;
    }

    public function withRunJob(string $name): RunnerInterface
    {
        $new = clone $this;
        $job = $new->run()->workflow()->jobs()->get($name);
        foreach ($job->runIf() as $runIf) {
            if ($new->getRunIfCondition($runIf) === false) {
                $new->addJobSkip($name);

                return $new;
            }
        }
        foreach ($job->dependencies() as $dependency) {
            try {
                $new->run()->response($dependency);
            } catch (OutOfBoundsException) {
                $new->addJobSkip($name);

                return $new;
            }
        }
        $arguments = $new->getJobArguments($job);
        $action = $job->action();

        try {
            $response = cast($action(...$arguments));
        } catch (Throwable $e) {
            throw new RunnerException(
                name: $name,
                job: $job,
                throwable: $e,
            );
        }
        $new->addJobResponse($name, $response);

        return $new;
    }

    private function getRunIfCondition(
        ConjunctionInterface|VariableInterface|ResponseReferenceInterface|callable $runIf
    ): bool
    {
        if ($runIf instanceof ConjunctionInterface) {
            $results = [];

            foreach ($runIf->getIterator() as $runIfCondition) {
                $results[] = $this
                    ->getRunIfCondition($runIfCondition);
            }

            $filter = array_filter($results);

            return match ($runIf->conjunction()) {
                ConjunctionInterface::CONJUNCTION_NOR => empty($filter),
                ConjunctionInterface::CONJUNCTION_OR => !empty($filter),
                ConjunctionInterface::CONJUNCTION_AND => ($results == $filter),
                default => false
            };
        }

        /** @var boolean */
        return match(true) {
            $runIf instanceof VariableInterface =>
                $this->run->arguments()->required($runIf->__toString())->bool(),

            $runIf instanceof ResponseReferenceInterface =>
                ($this->run->workflow()->jobs()->get($runIf->job())->action()::return() instanceof BoolParameterInterface) ?
                    $this->run->response($runIf->job())->bool() :
                    $this->run->response($runIf->job())->array()[$runIf->key()],

            default =>
                call_user_func($runIf, $this->run())
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getJobArguments(JobInterface $job): array
    {
        $arguments = [];
        foreach ($job->arguments() as $name => $value) {
            if ($value instanceof VariableInterface ||
                $value instanceof ResponseReferenceInterface ||
                $value instanceof ConjunctionInterface
            ) {
                try {
                    $arguments[$name] = $this
                        ->processJobArgument($value);

                } catch (Throwable $e) {
                    throw new RunnerException($name, $job, $e);
                }

                continue;
            }

            $arguments[$name] = $value;
        }

        return $arguments;
    }

    private function processJobArgument(
        ConjunctionInterface|VariableInterface|ResponseReferenceInterface|callable $value
    ): mixed
    {
        if ($value instanceof ConjunctionInterface) {
            if ($value->conjunction() !== ConjunctionInterface::CONJUNCTION_OR) {
                throw new RunnerException('');
            }

            /** @var ConjunctionInterface|VariableInterface|ResponseReferenceInterface|callable $conditional */
            foreach ($value->getIterator() as $conditional) {
                $val = $this
                    ->processJobArgument($conditional);

                if ($val !== null) {
                    return $val;
                }
            }

            throw new InvalidArgumentException();
        }

        return match(true) {
            $value instanceof VariableInterface =>
                $this->run->arguments()->get($value->__toString()),

            $value instanceof ResponseReferenceInterface =>
                ($value->key() !== null) ?
                    $this->run->response($value->job())->array()[$value->key()] :
                    $this->run->response($value->job())->mixed(),

            default =>
                call_user_func($value, $this->run())
        };
    }

    private function addJobResponse(string $name, CastInterface $response): void
    {
        $this->run = $this->run->withResponse($name, $response);
    }

    private function addJobSkip(string $name): void
    {
        if ($this->run->skip()->contains($name)) {
            return;
        }
        $this->run = $this->run->withSkip($name);
    }

    /**
     * @param array<string> $queue
     * @return array<Execution<mixed, never, never>>
     */
    private function getExecutions(array $queue): array
    {
        $return = [];
        foreach ($queue as $job) {
            $return[] = submit(
                new CallableTask(
                    'Chevere\\Workflow\\runnerForJob',
                    $this,
                    $job,
                )
            );
        }

        return $return;
    }

    private function merge(self $self, RunnerInterface $runner): void
    {
        foreach ($runner->run() as $name => $response) {
            $self->addJobResponse($name, $response);
        }
        foreach ($runner->run()->skip() as $name) {
            $self->addJobSkip($name);
        }
    }
}
