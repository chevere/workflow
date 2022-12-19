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

use Chevere\DataStructure\Interfaces\MapInterface;
use Chevere\DataStructure\Map;
use function Chevere\Message\message;
use function Chevere\Parameter\booleanParameter;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\Parameters;
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
     * @var MapInterface<string[]>
     */
    private MapInterface $expected;

    /**
     * @var MapInterface<ParametersInterface>
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

    public function withAddedJob(JobInterface ...$job): WorkflowInterface
    {
        $new = clone $this;
        $new->jobs = $new->jobs->withAdded(...$job);
        $new->putAdded(...$job);

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
    public function getJobResponseParameters(string $job): ParametersInterface
    {
        /** @var ParametersInterface */
        return $this->provided->get($job);
    }

    private function putParameters(string $name, JobInterface $job): void
    {
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

    private function putVariable(
        VariableInterface $variable,
        ParameterInterface $parameter
    ): void {
        if ($this->parameters->has($variable->__toString())) {
            return;
        }
        $this->parameters = $this->parameters
            ->withAddedRequired(...[
                $variable->__toString() => $parameter,
            ]);
    }

    private function assertPreviousReference(
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
        $isVariable = $value instanceof VariableInterface;
        $isReference = $value instanceof ReferenceInterface;
        if (! ($isVariable || $isReference)) {
            return;
        }
        if ($isVariable) {
            /** @var VariableInterface $value */
            $this->putVariable($value, $parameter);

            return;
        }
        /** @var ReferenceInterface $value */
        $this->assertPreviousReference($value);

        try {
            /** @var array<string> $expected */
            $expected = $this->expected->get($value->job());
        } catch (OutOfRangeException) {
            $expected = [];
        }
        $expected[] = $value->parameter();
        $this->expected = $this->expected
            ->withPut(...[
                $value->job() => $expected,
            ]);
    }
}
