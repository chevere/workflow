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

use Chevere\DataStructure\Map;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\Parameters;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use OutOfBoundsException;
use function Chevere\Action\getParameters;
use function Chevere\Parameter\bool;

final class Workflow implements WorkflowInterface
{
    private ParametersInterface $parameters;

    /**
     * @var Map<string[]>
     */
    private Map $expected;

    /**
     * @var Map<ParameterInterface>
     */
    private Map $provided;

    public function __construct(
        private JobsInterface $jobs
    ) {
        $this->parameters = new Parameters();
        $this->expected = new Map();
        $this->provided = new Map();
        $this->putAdded(
            ...iterator_to_array(
                $jobs->getIterator()
            )
        );
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
     * @throws OutOfBoundsException
     */
    public function getJobResponseParameter(string $job): ParameterInterface
    {
        /** @var ParameterInterface */
        return $this->provided->get($job);
    }

    private function putParameters(string $name, JobInterface $job): void
    {
        $action = $job->action();
        $parameters = getParameters($action::class);
        $this->provided = $this->provided->withPut($name, $action::return());
        foreach ($job->arguments() as $argument => $value) {
            $parameter = $parameters->get($argument);
            $this->putVariableReference($value, $parameter);
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
            ->withRequired(
                $variable->__toString(),
                $parameter,
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
        $parameter = bool();
        foreach ($job->runIf() as $value) {
            $this->putVariableReference($value, $parameter);
        }
    }

    private function putVariableReference(
        mixed $value,
        ParameterInterface $parameter
    ): void {
        $isVariable = $value instanceof VariableInterface;
        $isResponse = $value instanceof ResponseReferenceInterface;
        if (! ($isVariable || $isResponse)) {
            return;
        }
        if ($isVariable) {
            /** @var VariableInterface $value */
            $this->putVariable($value, $parameter);

            return;
        }

        /** @var ResponseReferenceInterface $value */
        try {
            /** @var array<string> $expected */
            $expected = $this->expected->get($value->job());
        } catch (OutOfBoundsException) {
            $expected = [];
        }
        $expected[] = $value->key()
            ?? $value->job();
        $this->expected = $this->expected
            ->withPut($value->job(), $expected);
    }
}
