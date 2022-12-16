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
use Chevere\DataStructure\Map;
use function Chevere\Message\message;
use function Chevere\Parameter\booleanParameter;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\Parameters;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\OutOfRangeException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\ReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;

final class Workflow implements WorkflowInterface
{
    private ParametersInterface $parameters;

    /**
     * @var MapInterface<string, string[]>
     */
    private MapInterface $expected;

    /**
     * @var MapInterface<string, ParametersInterface>
     */
    private MapInterface $provided;

    public function __construct(
        private JobsInterface $jobs
    ) {
        $this->parameters = new Parameters();
        $this->expected = new Map();
        $this->provided = new Map();
        $this->putAdded(...iterator_to_array($jobs->getIterator()));
    }

    public function jobs(): JobsInterface
    {
        return $this->jobs;
    }

    public function count(): int
    {
        return $this->jobs->count();
    }

    public function withAddedJob(JobInterface ...$jobs): WorkflowInterface
    {
        $new = clone $this;
        $new->jobs = $new->jobs->withAdded(...$jobs);
        $new->putAdded(...$jobs);

        return $new;
    }

    public function parameters(): ParametersInterface
    {
        return $this->parameters;
    }

    /**
     * @throws \TypeError
     * @throws OutOfRangeException
     */
    public function getJobReturnArguments(string $job): ParametersInterface
    {
        /** @var ParametersInterface */
        return $this->provided->get($job);
    }

    private function putParameters(string $name, JobInterface $job): void
    {
        /** @var ActionInterface $action */
        $action = $job->getAction();
        $parameters = $action->parameters();
        $this->provided = $this->provided->withPut(
            ...[
                $name => $action->responseParameters(),
            ]
        );
        foreach ($job->arguments() as $argument => $value) {
            try {
                $parameter = $parameters->get($argument);
                $this->putVariableReference($value, $parameter);
            } catch (OutOfRangeException $e) {
                throw new OutOfRangeException(
                    message('Incompatible declaration on Job %name% (%arg%) [%message%]')
                        ->withStrong('%name%', $name)
                        ->withStrong('%arg%', "argument@{$argument}")
                        ->withTranslate('%message%', $e->getMessage()),
                    previous: $e,
                );
            }
        }
    }

    private function assertMatchesExistingParameter(
        string $name,
        ParameterInterface $existent,
        ParameterInterface $parameter
    ): void {
        if ($existent::class !== $parameter::class) {
            throw new TypeError(
                message("Reference %name% of type %type% doesn't match type %provided%")
                    ->withStrong('%name%', $name)
                    ->withCode('%type%', $existent::class)
                    ->withCode('%provided%', $parameter::class),
            );
        }
    }

    private function putVariable(
        VariableInterface $variable,
        ParameterInterface $parameter
    ): void {
        if ($this->parameters->has($variable->__toString())) {
            $existent = $this->parameters->get($variable->__toString());
            $this->assertMatchesExistingParameter(
                $variable->__toString(),
                $existent,
                $parameter
            );

            return;
        }
        $this->parameters = $this->parameters
            ->withAddedRequired(...[
                $variable->__toString() => $parameter,
            ]);
    }

    private function assertPreviousReference(
        ParameterInterface $parameter,
        ReferenceInterface $reference
    ): void {
        /** @var ParametersInterface $responseParameters */
        $responseParameters = $this->provided->get($reference->job());
        if (! $responseParameters->has($reference->parameter())) {
            throw new OutOfRangeException(
                message('Reference %reference% not found, response key %parameter% is not declared by %job%')
                    ->withCode('%reference%', $reference->__toString())
                    ->withStrong('%parameter%', $reference->parameter())
                    ->withStrong('%job%', $reference->job()),
            );
        }
        $this->assertMatchesExistingParameter(
            $reference->__toString(),
            $responseParameters->get($reference->parameter()),
            $parameter
        );
    }

    private function putAdded(JobInterface ...$job): void
    {
        foreach ($job as $name => $item) {
            $name = strval($name);
            $this->putJobConditions($item);
            $this->putParameters($name, $item);
        }
    }

    private function putJobConditions(JobInterface $job): void
    {
        $parameter = booleanParameter();
        foreach ($job->runIf() as $value) {
            $this->putVariableReference($value, $parameter);
        }
    }

    private function putVariableReference(
        mixed $value,
        ParameterInterface $parameter
    ): void {
        if ($value instanceof VariableInterface) {
            $this->putVariable($value, $parameter);
        }
        if ($value instanceof ReferenceInterface) {
            $this->assertPreviousReference($parameter, $value);

            try {
                /** @var array<string[]> $expected */
                $expected = $this->expected->get($value->job());
            } catch(OutOfRangeException) {
                $expected = [];
            }
            $expected[] = $value->parameter();
            $this->expected = $this->expected
                ->withPut(...[
                    $value->job() => $expected,
                ]);
        }
    }
}
