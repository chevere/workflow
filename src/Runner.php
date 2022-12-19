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
use Chevere\Throwable\Exceptions\OutOfBoundsException;
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
        foreach ($jobs->graph() as $node) {
            $promises = $new->getPromises($node);
            /** @var RunnerInterface[] $responses */
            $responses = wait(all($promises));
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
                $new->run()->getResponse($dependency);
            } catch (OutOfBoundsException) {
                $new->addJobSkip($name);

                return $new;
            }
        }
        $action = $job->getAction()->withContainer($new->container);
        $arguments = $new->getJobArguments($job);
        $response = $new->getActionResponse($action, $arguments);
        $new->addJobResponse($name, $response);

        return $new;
    }

    private function getRunIfCondition(VariableInterface | ReferenceInterface $runIf): bool
    {
        /** @var boolean */
        return $runIf instanceof VariableInterface
                ? $this->run->arguments()->getBoolean($runIf->__toString())
                : $this->run->getResponse($runIf->job())->data()[$runIf->parameter()];
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
        foreach ($job->arguments() as $name => $value) {
            $isReference = $value instanceof ReferenceInterface;
            $isVariable = $value instanceof VariableInterface;
            if (! ($isReference || $isVariable)) {
                $arguments[$name] = $value;

                continue;
            }
            if ($isVariable) {
                /** @var VariableInterface $value */
                $arguments[$name] = $this->run->arguments()
                    ->get($value->__toString());

                continue;
            }
            /** @var ReferenceInterface $value */
            $arguments[$name] = $this->run->getResponse($value->job())
                ->data()[$value->parameter()];
        }

        return $arguments;
    }

    private function addJobResponse(string $name, ResponseInterface $response): void
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
