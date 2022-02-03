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

use Chevere\Action\Interfaces\ActionInterface;
use Chevere\DataStructure\Interfaces\MapInterface;
use Chevere\Dependent\Interfaces\DependentInterface;
use Chevere\Message\Message;
use Chevere\Parameter\Arguments;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\LogicException;
use Chevere\Throwable\Exceptions\RuntimeException;
use function Chevere\VarSupport\deepCopy;
use Chevere\Workflow\Interfaces\StepInterface;
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

    public function withRun(MapInterface $serviceContainer): static
    {
        $this->assertDependencies($serviceContainer);
        $new = clone $this;
        foreach ($new->workflowRun->workflow()->steps()->getIterator() as $name => $step) {
            if ($new->workflowRun->has($name)) {
                continue;
            }

            try {
                $actionName = $step->action();
                /** @var ActionInterface $action */
                $action = new $actionName();
                $new->injectDependencies($action, $serviceContainer);
                $arguments = $new->getActionArguments($action, $step);
                $response = $new->getActionRunResponse($action, $arguments);
                deepCopy($response);
                $new->addStep($name, $response);
            }
            // @codeCoverageIgnoreStart
            catch (Throwable $e) {
                throw new RuntimeException(
                    previous: $e,
                    message: (new Message('Caught %throwable% at step:%step% when running action:%action%'))
                        ->code('%throwable%', $e::class)
                        ->code('%step%', $name)
                        ->code('%action%', $actionName)
                );
            }
            // @codeCoverageIgnoreEnd
        }

        return $new;
    }

    private function getActionRunResponse(ActionInterface $action, ArgumentsInterface $arguments): ResponseInterface
    {
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

    private function getActionArguments(ActionInterface $action, StepInterface $step): ArgumentsInterface
    {
        $arguments = $this->getStepArguments($step);

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

    private function assertDependencies(MapInterface $serviceContainer): void
    {
        $dependencies = $this->workflowRun->workflow()->steps()->dependencies();
        $missing = [];
        foreach ($dependencies->getIterator() as $name => $className) {
            $isMissing =
                !$serviceContainer->has($name) ||
                !is_a($serviceContainer->get($name), $className, false);
            if ($isMissing) {
                $missing[] = "${name}:${className}";
            }
        }
        if ($missing !== []) {
            throw new LogicException(
                message: (new Message('Missing %missing% dependency(ies)'))
                    ->code('%missing%', implode(', ', $missing))
            );
        }
    }

    private function injectDependencies(ActionInterface &$action, MapInterface $serviceContainer): void
    {
        if ($action instanceof DependentInterface) {
            $instances = [];
            foreach ($action->dependencies()->getIterator() as $name => $className) {
                $instances[$name] = $serviceContainer->get($name);
            }
            $action = $action->withDependencies(...$instances);
        }
    }

    private function getStepArguments(StepInterface $step): array
    {
        $arguments = [];
        foreach ($step->arguments() as $name => $taskArgument) {
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

    private function addStep(string $name, ResponseInterface $response): void
    {
        $this->workflowRun = $this->workflowRun
            ->withStepResponse($name, $response);
    }
}
