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

use function Amp\Parallel\Worker\enqueueCallable;
use function Amp\Promise\all;
use function Amp\Promise\wait;
use Chevere\Action\Interfaces\ActionInterface;
use function Chevere\Message\message;
use Chevere\Message\Message;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\RuntimeException;
use function Chevere\VarSupport\deepCopy;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\RunnerInterface;
use Psr\Container\ContainerInterface;
use Throwable;

final class Runner implements RunnerInterface
{
    /** @var RunnerInterface[] */
    private array $responses;

    public function __construct(
        private RunInterface $workflowRun,
        private ContainerInterface $container
    ) {
    }

    public function run(): RunInterface
    {
        return $this->workflowRun;
    }

    public function withRun(): RunnerInterface
    {
        $new = clone $this;
        $jobs = $new->workflowRun->workflow()->jobs();
        $promises = [];
        foreach ($jobs->getGraph() as $jobs) {
            foreach ($jobs as $job) {
                $promises[] = enqueueCallable(
                    'Chevere\\Workflow\\workflowRunnerForJob',
                    $new,
                    $job,
                );
            }

            try {
                /** @var RunnerInterface[] $responses */
                $responses = wait(all($promises));
                // @phpstan-ignore-next-line
                $new->responses = $responses;
            }
            // @codeCoverageIgnoreStart
            catch (Throwable $e) {
                throw new RuntimeException(
                    message('Error running job %job% [%message%]')
                        ->code('%job%', $job ?? ':before')
                        ->strtr('%message%', $e->getMessage()),
                    previous: $e
                );
            }
            // @codeCoverageIgnoreEnd
            /** @var RunnerInterface $new */
            $new = end($new->responses);
        }

        return $new;
    }

    public function withRunJob(string $name): RunnerInterface
    {
        $job = $this->run()->workflow()->jobs()->get($name);
        $actionName = $job->action();
        /** @var ActionInterface $action */
        $action = new $actionName();
        $action = $action->withContainer($this->container);
        $arguments = $this->getJobArguments($job);
        $response = $this->getActionResponse($action, $arguments);
        deepCopy($response);
        $new = clone $this;
        $new->addJob($name, $response);

        return $new;
    }

    /**
     * @param Array<string, mixed> $arguments
     */
    private function getActionResponse(
        ActionInterface $action,
        array $arguments
    ): ResponseInterface {
        try {
            return $action->getResponse(...$arguments);
        }
        // @codeCoverageIgnoreStart
        catch (Throwable $e) {
            $actionTrace = $e->getTrace()[1] ?? [];
            $fileLine = strtr('%file%:%line%', [
                '%file%' => $actionTrace['file'] ?? 'anon',
                '%line%' => $actionTrace['line'] ?? '0',
            ]);

            throw new InvalidArgumentException(
                previous: $e,
                message: (new Message('%message% at %fileLine% for action %action%'))
                    ->strtr('%message%', $e->getMessage())
                    ->code('%fileLine%', $fileLine)
                    ->code('%action%', $action::class)
            );
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return Array<string, mixed>
     */
    private function getJobArguments(JobInterface $job): array
    {
        $arguments = [];
        foreach ($job->arguments() as $name => $argument) {
            if (!is_string($argument)
                || !$this->workflowRun->workflow()->vars()->has($argument)
            ) {
                // @codeCoverageIgnoreStart
                $arguments[$name] = $argument;

                continue;
                // @codeCoverageIgnoreEnd
            }
            $reference = $this->workflowRun->workflow()->getVar($argument);
            if (isset($reference[1])) {
                // @codeCoverageIgnoreStart
                $arguments[$name] = $this->workflowRun
                    ->get($reference[0])
                    ->data()[$reference[1]];
            // @codeCoverageIgnoreEnd
            } else {
                $arguments[$name] = $this->workflowRun
                    ->arguments()->get($reference[0]);
            }
        }

        return $arguments;
    }

    private function addJob(string $name, ResponseInterface $response): void
    {
        $this->workflowRun = $this->workflowRun
            ->withJobResponse($name, $response);
    }
}