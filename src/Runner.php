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
use Chevere\Action\Interfaces\ActionInterface;
use Chevere\Parameter\Interfaces\CastInterface;
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
            $responses = await(array_map(
                fn (Execution $e) => $e->getFuture(),
                $executions,
            ));
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
                $new->run()->getReturn($dependency);
            } catch (OutOfBoundsException) {
                $new->addJobSkip($name);

                return $new;
            }
        }
        $arguments = $new->getJobArguments($job);
        $action = $job->action();
        $response = $new->getActionResponse($action, $arguments);
        $new->addJobResponse($name, $response);

        return $new;
    }

    private function getRunIfCondition(VariableInterface|ResponseReferenceInterface $runIf): bool
    {
        /** @var boolean */
        return $runIf instanceof VariableInterface
                ? $this->run->arguments()->required($runIf->__toString())->bool()
                : $this->run->getReturn($runIf->job())->array()[$runIf->key()];
    }

    /**
     * @phpstan-ignore-next-line
     */
    private function getActionResponse(
        ActionInterface $action,
        array $arguments
    ): CastInterface {
        try {
            return cast($action->__invoke(...$arguments));
        } catch (Throwable $e) { // @codeCoverageIgnoreStart
            $actionTrace = $e->getTrace()[1] ?? [];
            $fileLine = strtr('%file%:%line%', [
                '%file%' => $actionTrace['file'] ?? 'anon',
                '%line%' => $actionTrace['line'] ?? '0',
            ]);

            throw new InvalidArgumentException(
                previous: $e,
                message: (string) message(
                    '%message% at `%fileLine%` for action `%action%`',
                    message: $e->getMessage(),
                    fileLine: $fileLine,
                    action: $action::class,
                )
            );
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return array<string, mixed>
     */
    private function getJobArguments(JobInterface $job): array
    {
        $arguments = [];
        foreach ($job->arguments() as $name => $value) {
            $isResponseReference = $value instanceof ResponseReferenceInterface;
            $isVariable = $value instanceof VariableInterface;
            if (! ($isResponseReference || $isVariable)) {
                $arguments[$name] = $value;

                continue;
            }
            if ($isVariable) {
                /** @var VariableInterface $value */
                $arguments[$name] = $this->run->arguments()
                    ->get($value->__toString());

                continue;
            }
            /** @var ResponseReferenceInterface $value */
            if ($value->key() !== null) {
                $arguments[$name] = $this->run->getReturn($value->job())->array()[$value->key()];

                continue;
            }
            $arguments[$name] = $this->run->getReturn($value->job())->mixed();
        }

        return $arguments;
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
