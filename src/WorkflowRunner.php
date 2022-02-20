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
use Chevere\DataStructure\Interfaces\MapInterface;
use Chevere\Message\Message;
use Chevere\Parameter\Arguments;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\RuntimeException;
use function Chevere\VarSupport\deepCopy;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\WorkflowRunInterface;
use Chevere\Workflow\Interfaces\WorkflowRunnerInterface;
use Throwable;

final class WorkflowRunner implements WorkflowRunnerInterface
{
    public function __construct(
        private WorkflowRunInterface $workflowRun
    ) {
    }

    public function workflowRun(): WorkflowRunInterface
    {
        return $this->workflowRun;
    }

    public function withRun(MapInterface $serviceContainer): WorkflowRunnerInterface
    {
        $new = clone $this;
        $jobs = $new->workflowRun->workflow()->jobs();
        $promises = [];
        foreach ($jobs->getGraph() as $jobs) {
            $promises[] = enqueueCallable(
                'Chevere\\Workflow\\runJob',
                $new,
                ...$jobs,
            );
        }
        $responses = wait(all($promises));

        return end($responses);
    }

    private function getActionRunResponse(
        ActionInterface $action,
        ArgumentsInterface $arguments
    ): ResponseInterface {
        try {
            return $action->run($arguments);
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
                message: (new Message('Missing argument(s) at %fileLine%'))
                    ->code('%fileLine%', $fileLine)
            );
        }
        // @codeCoverageIgnoreEnd
    }

    private function getActionArguments(ActionInterface $action, JobInterface $job): ArgumentsInterface
    {
        $arguments = $this->getJobArguments($job);

        try {
            return new Arguments($action->parameters(), ...$arguments);
        }
        // @codeCoverageIgnoreStart
        catch (Throwable $e) {
            throw new InvalidArgumentException(
                previous: $e,
                message: (new Message('Missing argument(s)'))
            );
        }
        // @codeCoverageIgnoreEnd
    }

    private function getJobArguments(JobInterface $job): array
    {
        $arguments = [];
        foreach ($job->arguments() as $name => $taskArgument) {
            if (!$this->workflowRun->workflow()->vars()->has($taskArgument)) {
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

    public function runJob(string $name, JobInterface $job): void
    {
        try {
            $actionName = $job->action();
            /** @var ActionInterface $action */
            $action = new $actionName();
            $arguments = $this->getActionArguments($action, $job);
            $response = $this->getActionRunResponse($action, $arguments);
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
}
