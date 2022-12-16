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
use Amp\Promise;
use function Amp\Promise\all;
use function Amp\Promise\wait;
use Chevere\Action\Interfaces\ActionInterface;
use function Chevere\Message\message;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ReferenceInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\RunnerInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Psr\Container\ContainerInterface;
use Throwable;

final class Runner implements RunnerInterface
{
    public function __construct(
        private RunInterface $run,
        private ContainerInterface $container
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
        foreach ($jobs->graph() as $queue) {
            $promises = $new->getPromises($queue);
            /** @var RunnerInterface[] $responses */
            $responses = wait(all($promises));
            /** @var self $end */
            $end = end($responses);
            if ($end->run()->has(...$queue)) {
                $new = $end;

                continue;
            }
            $new->mergeRunner($new, ...$responses);
        }

        return $new;
    }

    public function withRunJob(string $name): RunnerInterface
    {
        // $new = clone $this;
        $job = $this->run()->workflow()->jobs()->get($name);
        /** @var ActionInterface $action */
        $action = $job->getAction()
            ->withContainer($this->container);
        $arguments = $this->getJobArguments($job);
        $response = $this->getActionResponse($action, $arguments);
        $this->addJob($name, $response);

        return $this;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function getActionResponse(
        ActionInterface $action,
        array $arguments
    ): ResponseInterface {
        try {
            return $action->getResponse(...$arguments);
        } catch (Throwable $e) { // @codeCoverageIgnoreStart
            $actionTrace = $e->getTrace()[1] ?? [];
            $fileLine = strtr('%file%:%line%', [
                '%file%' => $actionTrace['file'] ?? 'anon',
                '%line%' => $actionTrace['line'] ?? '0',
            ]);

            throw new InvalidArgumentException(
                previous: $e,
                message: message('%message% at %fileLine% for action %action%')
                    ->withTranslate('%message%', $e->getMessage())
                    ->withCode('%fileLine%', $fileLine)
                    ->withCode('%action%', $action::class)
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
        foreach ($job->arguments() as $name => $argument) {
            if (! ($argument instanceof ReferenceInterface || $argument instanceof VariableInterface)) {
                $arguments[$name] = $argument;

                continue;
            }
            if ($argument instanceof VariableInterface) {
                $arguments[$name] = $this->run->arguments()
                    ->get($argument->__toString());

                continue;
            }
            $arguments[$name] = $this->run->get($argument->job())
                ->data()[$argument->parameter()];
        }

        return $arguments;
    }

    private function addJob(string $name, ResponseInterface $response): void
    {
        $this->run = $this->run
            ->withJobResponse($name, $response);
    }

    /**
     * @param array<string> $queue
     * @return array<Promise<mixed>>
     */
    private function getPromises(array $queue): array
    {
        $promises = [];
        foreach ($queue as $job) {
            $promises[] = enqueueCallable(
                'Chevere\\Workflow\\runnerForJob',
                $this,
                $job,
            );
        }

        return $promises;
    }

    private function mergeRunner(self $stock, RunnerInterface ...$merge): void
    {
        foreach ($merge as $runner) {
            foreach ($runner->run()->getIterator() as $name => $response) {
                $stock->addJob($name, $response);
            }
        }
    }
}
