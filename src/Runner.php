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

use Amp\Promise;
use Chevere\Action\Interfaces\ActionInterface;
use Chevere\Parameter\Interfaces\CastArgumentInterface;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\RunnerInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Psr\Container\ContainerInterface;
use Throwable;
use function Amp\Parallel\Worker\enqueueCallable;
use function Amp\Promise\all;
use function Amp\Promise\wait;
use function Chevere\Message\message;

final class Runner implements RunnerInterface
{
    public function __construct(
        private RunInterface $run,
        // @phpstan-ignore-next-line
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
        $arguments = $new->getJobArguments($job);
        $action = $job->actionName()->__toString();
        /** @var ActionInterface $action */
        $action = new $action();
        $response = $new->getActionResponse($action, $arguments);
        $new->addJobResponse($name, $response);

        return $new;
    }

    private function getRunIfCondition(VariableInterface|ResponseReferenceInterface $runIf): bool
    {
        /** @var boolean */
        return $runIf instanceof VariableInterface
                ? $this->run->arguments()->required($runIf->__toString())->boolean()
                : $this->run->getResponse($runIf->job())->array()[$runIf->key()];
    }

    /**
     * @phpstan-ignore-next-line
     */
    private function getActionResponse(
        ActionInterface $action,
        array $arguments
    ): CastArgumentInterface {
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
            if ($value->key() === null) {
                $arguments[$name] = $this->run->getResponse($value->job())->mixed();

                continue;
            }

            /** @var ResponseReferenceInterface $value */
            $arguments[$name] = $this->run->getResponse($value->job())->array()[$value->key()];
        }

        return $arguments;
    }

    private function addJobResponse(string $name, CastArgumentInterface $response): void
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
