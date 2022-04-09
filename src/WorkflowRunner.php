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
use Chevere\Message\Message;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\RuntimeException;
use function Chevere\VarSupport\deepCopy;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\WorkflowRunInterface;
use Chevere\Workflow\Interfaces\WorkflowRunnerInterface;
use Psr\Container\ContainerInterface;
use Throwable;

final class WorkflowRunner implements WorkflowRunnerInterface
{
    public function __construct(
        private WorkflowRunInterface $workflowRun,
        private ContainerInterface $container
    ) {
    }

    public function workflowRun(): WorkflowRunInterface
    {
        return $this->workflowRun;
    }

    public function withRun(): WorkflowRunnerInterface
    {
        $new = clone $this;
        $jobs = $new->workflowRun->workflow()->jobs();
        $promises = [];
        foreach ($jobs->getGraph() as $jobs) {
            foreach ($jobs as $job) {
                $promises[] = enqueueCallable(
                    'Chevere\\Workflow\\workflowRunner',
                    $new,
                    $job,
                );
            }
            $responses = wait(all($promises));
            $new = end($responses);
        }
        
        return $new;
    }

    public function runJob(string $name): void
    {
        try {
            $job = $this->workflowRun()->workflow()->jobs()->get($name);
            $actionName = $job->action();
            /** @var ActionInterface $action */
            $action = new $actionName();
            $action = $action->withContainer($this->container);
            $arguments = $this->getJobArguments($job);
            $response = $this->getActionResponse($action, $arguments);
            deepCopy($response);
            $this->addJob($name, $response);
        }
        // @codeCoverageIgnoreStart
        catch (Throwable $e) {
            throw new RuntimeException(
                previous: $e,
                message: (new Message('Caught %throwable% at job:%job% when running action:%action%'))
                    ->code('%throwable%', $e::class)
                    ->code('%job%', $name)
                    ->code('%action%', $actionName)
            );
        }
        // @codeCoverageIgnoreEnd
    }

    private function getActionResponse(
        ActionInterface $action,
        array $arguments
    ): ResponseInterface {
        try {
            return $action->runner(...$arguments);
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

    private function getJobArguments(JobInterface $job): array
    {
        $arguments = [];
        foreach ($job->arguments() as $name => $taskArgument) {
            if (!is_string($taskArgument) || !$this->workflowRun->workflow()->vars()->has($taskArgument)) {
                // @codeCoverageIgnoreStart
                $arguments[$name] = $taskArgument;

                continue;
                // @codeCoverageIgnoreEnd
            }
            $reference = $this->workflowRun->workflow()->getVar($taskArgument);
            if (isset($reference[1])) {
                $arguments[$name] = $this->workflowRun
                    ->get($reference[0])->data()[$reference[1]];
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
