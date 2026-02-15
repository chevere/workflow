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

use Chevere\Container\Dependencies;
use Chevere\Container\Interfaces\DependenciesInterface;
use Chevere\DataStructure\Interfaces\MapInterface;
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
use function Chevere\Parameter\bool;

final class Workflow implements WorkflowInterface
{
    private ParametersInterface $parameters;

    /**
     * @var Map<string[]>
     */
    private Map $referenced;

    /**
     * @var Map<ParameterInterface>
     */
    private Map $provided;

    private DependenciesInterface $dependencies;

    public function __construct(
        private JobsInterface $jobs
    ) {
        $this->parameters = new Parameters();
        $this->referenced = new Map();
        $this->provided = new Map();
        $this->dependencies = new Dependencies();
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

    /**
     * @return MapInterface<string[]>
     */
    public function referenced(): MapInterface
    {
        return $this->referenced;
    }

    public function dependencies(): DependenciesInterface
    {
        return $this->dependencies;
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

    private function putParameters(string $name, JobInterface $job): void
    {
        $parameters = $job->parameters();
        $positions = array_keys($parameters->keys());
        $lastKey = array_key_last($parameters->keys());
        $this->provided = $this->provided->withPut(
            $name,
            $job->return()
        );
        foreach ($job->arguments() as $id => $value) {
            $id = strval($id);
            if (! $parameters->has($id)) {
                $search = array_search($id, $positions);
                $id = $parameters->keys()[$search === false ? $lastKey : $search];
            }
            $parameter = $parameters->get($id);
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
            if (is_string($item->action())) {
                $this->dependencies = $this->dependencies
                    ->withClass($item->action());
            }
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
            $expected = $this->referenced->get($value->job());
        } catch (OutOfBoundsException) {
            $expected = [];
        }
        $expected[] = $value->key() ?? $value->job();
        $this->referenced = $this->referenced
            ->withPut($value->job(), $expected);
    }
}
