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

use Amp\CancelledException;
use Amp\Future;
use Amp\TimeoutCancellation;
use Chevere\Action\Interfaces\ActionInterface;
use Chevere\Workflow\Exceptions\RunnerException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\RunnerInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use OutOfBoundsException;
use Throwable;
use function Amp\async;
use function Amp\delay;
use function Amp\Future\await;

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
            $responses = await($executions);
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
        if (is_string($action)) {
            $dependencies = $this->run->workflow()->dependencies()->extract(
                $action,
                $this->run->container()
            );
            /** @var ActionInterface $action */
            $action = new $action(...$dependencies);
        }
        if ($action instanceof ActionInterface) {
            $action->assert();
        }
        $arguments = $job->parameters()->__invoke(...$arguments)->toArray();
        $retryPolicy = $job->retryPolicy();
        $maxAttempts = $retryPolicy->maxAttempts();
        $delay = $retryPolicy->delay();
        $timeout = $retryPolicy->timeout();
        $cancellation = $timeout > 0
            ? new TimeoutCancellation($timeout)
            : null;
        $lastException = null;
        $response = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $currentAttempt = $attempt;

            try {
                if ($cancellation !== null) {
                    $response = await(
                        [
                            async(fn (): mixed => $action->__invoke(...$arguments)),
                        ],
                        cancellation: $cancellation
                    )[0];
                } else {
                    $response = $action->__invoke(...$arguments);
                }
                $lastException = null;

                break;
            } catch (Throwable $e) {
                $lastException = $e;
                if ($e instanceof CancelledException) {
                    break;
                }
                if ($attempt < $maxAttempts && $delay > 0) {
                    delay($delay);
                }
            }
        }
        if ($lastException !== null) {
            throw new RunnerException(
                name: $name,
                job: $job,
                throwable: $lastException,
                attempt: $currentAttempt,
            );
        }
        $new->addJobResponse($name, $response);

        return $new;
    }

    private function getRunIfCondition(
        VariableInterface|ResponseReferenceInterface|callable|bool $runIf
    ): bool {
        /** @var boolean */
        return match (true) {
            is_bool($runIf) => $runIf,
            $runIf instanceof VariableInterface => $this->run->arguments()->required($runIf->__toString())->bool(),
            $runIf instanceof ResponseReferenceInterface => $this->run->response($runIf->job())->array()[$runIf->key()],
            default => call_user_func($runIf, $this->run())
        };
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
                $arguments[$name] = $this->run->response($value->job())->array()[$value->key()];

                continue;
            }
            $arguments[$name] = $this->run->response($value->job())->mixed();
        }

        return $arguments;
    }

    private function addJobResponse(string $name, mixed $response): void
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
     * @return array<Future<mixed>>
     */
    private function getExecutions(array $queue): array
    {
        $return = [];
        foreach ($queue as $job) {
            $return[] = async(
                fn () => runnerForJob($this, $job)
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
