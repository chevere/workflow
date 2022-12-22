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
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\DataStructure\Map;
use Chevere\DataStructure\Traits\MapTrait;
use Chevere\DataStructure\Vector;
use function Chevere\DataStructure\vectorToArray;
use function Chevere\Message\message;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Type\Interfaces\TypeInterface;
use function Chevere\Type\typeBoolean;
use Chevere\Workflow\Interfaces\GraphInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\ReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;

final class Jobs implements JobsInterface
{
    /**
     * @template-use MapTrait<JobInterface>
     */
    use MapTrait;

    /**
     * @var VectorInterface<string>
     */
    private VectorInterface $jobs;

    private GraphInterface $graph;

    /**
     * @var MapInterface<TypeInterface>
     */
    private MapInterface $variables;

    /**
     * @var MapInterface<TypeInterface>
     */
    private MapInterface $references;

    /**
     * @var VectorInterface<string>
     */
    private VectorInterface $jobDependencies;

    public function __construct(JobInterface ...$jobs)
    {
        $this->map = new Map();
        $this->jobs = new Vector();
        $this->graph = new Graph();
        $this->variables = new Map();
        $this->references = new Map();
        $this->putAdded(...$jobs);
    }

    public function graph(): array
    {
        return $this->graph->toArray();
    }

    public function variables(): MapInterface
    {
        return $this->variables;
    }

    public function references(): MapInterface
    {
        return $this->references;
    }

    public function get(string $job): JobInterface
    {
        /** @var JobInterface */
        return $this->map->get($job);
    }

    public function has(string $job): bool
    {
        return $this->map->has($job);
    }

    public function withAdded(JobInterface ...$jobs): JobsInterface
    {
        $new = clone $this;
        $new->putAdded(...$jobs);

        return $new;
    }

    private function addMap(string $name, JobInterface $job): void
    {
        if ($this->map->has($name)) {
            throw new OverflowException(
                message('Job name %name% has been already added.')
                    ->withCode('%name%', $name)
            );
        }
        $this->map = $this->map->withPut(...[
            $name => $job,
        ]);
    }

    private function putAdded(JobInterface ...$job): void
    {
        foreach ($job as $name => $item) {
            $this->jobDependencies = $item->dependencies();
            $name = strval($name);
            $this->addMap($name, $item);
            $this->jobs = $this->jobs->withPush($name);
            $this->handleArguments($name, $item);
            foreach ($item->runIf() as $runIf) {
                $this->handleRunIfReference($runIf);
                $this->handleRunIfVariable($name, $runIf);
            }
            $this->storeReferences($name, $item);
            $this->assertDependencies($name);
            $this->graph = $this->graph->withPut($name, $item);
        }
    }

    private function storeReferences(string $name, JobInterface $job): void
    {
        $action = $job->action();
        foreach ($action->responseParameters() as $key => $parameter) {
            $this->references = $this->references
                ->withPut(
                    ...[
                        strval(reference($name, $key)) => $parameter->type(),
                    ]
                );
        }
    }

    private function handleArguments(string $name, JobInterface $job): void
    {
        foreach ($job->arguments() as $parameter => $argument) {
            $action = $job->action();
            /** @var TypeInterface $type */
            $type = $action->parameters()->get($parameter)->type();
            $property = match (true) {
                $argument instanceof VariableInterface => 'variables',
                $argument instanceof ReferenceInterface => 'references',
                default => false
            };
            if (! $property) {
                continue;
            }
            /** @var VariableInterface|ReferenceInterface $argument */
            try {
                $this->mapSubjectType($property, $type, $argument);
            } catch (TypeError $e) {
                throw new TypeError(
                    message($e->getMessage())
                        ->withTranslate('%parameter%', $parameter)
                        ->withTranslate('%job%', $name)
                );
            }
        }
    }

    private function mapSubjectType(
        string $property,
        TypeInterface $type,
        VariableInterface|ReferenceInterface $argument,
    ): void {
        /** @var MapInterface<TypeInterface> $map */
        $map = $this->{$property};
        $subject = 'Reference';
        $key = strval($argument);
        if ($argument instanceof VariableInterface) {
            $subject = 'Variable';
            $key = $argument->__toString();
        }
        if (! $map->has($key)) {
            $map = $map->withPut(...[
                $key => $type,
            ]);
            $this->{$property} = $map;

            return;
        }
        /** @var TypeInterface $typeStored */
        $typeStored = $map->get($key);
        if ($typeStored->primitive() !== $type->primitive()) {
            throw new TypeError(
                message('%subject% %key% is of type %type%, parameter %parameter% expects %typeExpected% on job %job%.')
                    ->withCode('%type%', $typeStored->primitive())
                    ->withCode('%typeExpected%', $type->primitive())
                    ->withTranslate('%subject%', $subject)
                    ->withTranslate('%key%', $key)
            );
        }
    }

    private function handleRunIfReference(mixed $runIf): void
    {
        if (! $runIf instanceof ReferenceInterface) {
            return;
        }
        $action = $this->get($runIf->job())->action();
        $parameter = $action->getResponseParameters()
            ->get($runIf->parameter());
        if ($parameter->type()->primitive() !== 'boolean') {
            throw new TypeError(
                message('Reference %reference% must be of type boolean')
                    ->withCode('%reference%', $runIf->__toString())
            );
        }
    }

    private function handleRunIfVariable(string $name, mixed $runIf): void
    {
        if (! $runIf instanceof VariableInterface) {
            return;
        }
        if (! $this->variables->has($runIf->__toString())) {
            $this->variables = $this->variables
                ->withPut(
                    ...[
                        $runIf->__toString() => typeBoolean(),
                    ]
                );

            return;
        }
        /** @var TypeInterface $type */
        $type = $this->variables->get($runIf->__toString());
        if ($type->primitive() !== 'boolean') {
            throw new TypeError(
                message('Variable %variable% (previously declared as %type%) is not of type boolean at job %job%')
                    ->withCode('%variable%', $runIf->__toString())
                    ->withCode('%type%', $type->primitive())
                    ->withCode('%job%', $name)
            );
        }
    }

    private function assertDependencies(string $job): void
    {
        $dependencies = vectorToArray($this->jobDependencies);
        if (! $this->jobs->contains(...$dependencies)) {
            $missing = array_diff(
                $dependencies,
                vectorToArray($this->jobs)
            );

            throw new OutOfBoundsException(
                message('Job %job% has undeclared dependencies: %dependencies%')
                    ->withCode('%job%', $job)
                    ->withCode('%dependencies%', implode(', ', $missing))
            );
        }
    }
}
