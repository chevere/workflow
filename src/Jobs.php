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
use Chevere\DataStructure\Map;
use Chevere\DataStructure\Traits\MapTrait;
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
use Ds\Vector;
use Iterator;

final class Jobs implements JobsInterface
{
    use MapTrait;

    /**
     * @var Vector<string>
     */
    private Vector $jobs;

    private GraphInterface $graph;

    private Map $variables;

    private Map $references;

    /**
     * @var Vector<string>
     */
    private Vector $jobDependencies;

    public function __construct(JobInterface ...$jobs)
    {
        $this->map = new Map();
        $this->jobs = new Vector();
        $this->graph = new Graph();
        $this->variables = new Map();
        $this->references = new Map();
        $this->putAdded(...$jobs);
    }

    public function keys(): array
    {
        return $this->jobs->toArray();
    }

    public function graph(): array
    {
        return $this->graph->toArray();
    }

    public function variables(): Map
    {
        return $this->variables;
    }

    public function references(): Map
    {
        return $this->references;
    }

    public function get(string $job): JobInterface
    {
        try {
            // @phpstan-ignore-next-line
            return $this->map->get($job);
        }
        // @codeCoverageIgnoreStart
        // @infection-ignore-all
        // @phpstan-ignore-next-line
        catch (\TypeError $e) {
            throw new TypeError(previous: $e);
        }
        // @codeCoverageIgnoreEnd
        catch (\OutOfBoundsException $e) {
            throw new OutOfBoundsException(
                message('Job %name% not found')
                    ->withCode('%name%', $job)
            );
        }
    }

    #[\ReturnTypeWillChange]
    public function getIterator(): Iterator
    {
        foreach ($this->jobs as $job) {
            yield $job => $this->get($job);
        }
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
        $this->map = $this->map->withPut($name, $job);
    }

    private function putAdded(JobInterface ...$jobs): void
    {
        foreach ($jobs as $name => $job) {
            $this->jobDependencies = new Vector($job->dependencies());
            $name = strval($name);
            $this->addMap($name, $job);
            $this->jobs->push($name);
            $this->handleArguments($name, $job);
            foreach ($job->runIf() as $runIf) {
                $this->handleRunIfReference($name, $runIf);
                $this->handleRunIfVariable($name, $runIf);
            }
            $this->storeReferences($name, $job);
            $this->assertDependencies($name);
            $this->graph = $this->graph->withPut($name, $job);
        }
    }

    private function storeReferences(string $name, JobInterface $job): void
    {
        /** @var ActionInterface $action */
        $action = new ($job->action());
        foreach ($action->responseParameters()->getIterator() as $key => $parameter) {
            $this->references = $this->references
                ->withPut(strval(reference($name, $key)), $parameter->type());
        }
    }

    private function handleArguments(string $name, JobInterface $job): void
    {
        foreach ($job->arguments() as $parameter => $argument) {
            /** @var ActionInterface $action */
            $action = new ($job->action());
            /** @var TypeInterface $type */
            $type = $action->parameters()->get($parameter)->type();
            $property = match (true) {
                $argument instanceof VariableInterface => 'variables',
                $argument instanceof ReferenceInterface => 'references',
                default => false
            };
            if (!$property) {
                continue;
            }

            try {
                $this->{$property} = $this->mapSubjectType(
                    $argument,
                    $this->{$property},
                    $type
                );
            } catch (TypeError $e) {
                throw new TypeError(
                    message($e->getMessage())
                        ->withStrtr('%parameter%', $parameter)
                        ->withStrtr('%job%', $name)
                );
            }
        }
    }

    private function mapSubjectType(
        VariableInterface|ReferenceInterface $argument,
        Map $map,
        TypeInterface $type
    ): Map {
        $subject = 'Reference';
        $key = strval($argument);
        if ($argument instanceof VariableInterface) {
            $subject = 'Variable';
            $key = $argument->__toString();
        }
        if (!$map->has($key)) {
            return $map->withPut($key, $type);
        }
        /** @var TypeInterface $typeStored */
        $typeStored = $map->get($key);
        if ($typeStored->primitive() !== $type->primitive()) {
            throw new TypeError(
                message('%subject% %key% is of type %type%, parameter %parameter% expects %typeExpected% on job %job%.')
                    ->withCode('%type%', $typeStored->primitive())
                    ->withCode('%typeExpected%', $type->primitive())
                    ->withStrtr('%subject%', $subject)
                    ->withStrtr('%key%', $key)
            );
        }

        return $map;
    }

    private function handleRunIfReference(string $job, $runIf): void
    {
        if (!$runIf instanceof ReferenceInterface) {
            return;
        }
        if ($runIf instanceof ReferenceInterface) {
            if (!$this->jobDependencies->contains($runIf->job())) {
                $this->jobDependencies->push($runIf->job());
            }
            /** @var JobInterface $runIfJob */
            try {
                $runIfJob = $this->map->get($runIf->job());
            } catch (OutOfBoundsException $e) {
                throw new OutOfBoundsException(
                    message('Job %job% not found')
                        ->withCode('%job%', $runIf->job())
                );
            }
            /** @var ActionInterface $action */
            $action = new ($runIfJob->action());
            $parameter = $action
                ->getResponseParameters()->get($runIf->parameter());
            if ($parameter->type()->primitive() !== 'boolean') {
                throw new TypeError(
                    message('Reference %reference% must be of type boolean')
                        ->withCode('%reference%', $runIf->__toString())
                );
            }
        }
    }

    private function handleRunIfVariable(string $job, $runIf): void
    {
        if (!$runIf instanceof VariableInterface) {
            return;
        }
        if (!$this->variables->has($runIf->__toString())) {
            $this->variables = $this->variables
                ->withPut($runIf->__toString(), typeBoolean());

            return;
        }
        /** @var TypeInterface $type */
        $type = $this->variables->get($runIf->__toString());
        if ($type->primitive() !== 'boolean') {
            throw new TypeError(
                message('Variable %variable% (previously declared as %type%) is not of type boolean at job %job%')
                    ->withCode('%variable%', $runIf->__toString())
                    ->withCode('%type%', $type->primitive())
                    ->withCode('%job%', $job)
            );
        }
    }

    private function assertDependencies(string $job): void
    {
        $dependencies = $this->jobDependencies->toArray();
        if (!$this->jobs->contains(...$dependencies)) {
            $missing = array_diff($dependencies, $this->jobs->toArray());

            throw new OutOfBoundsException(
                message('Job %job% has undeclared dependencies: %dependencies%')
                    ->withCode('%job%', $job)
                    ->withCode('%dependencies%', implode(', ', $missing))
            );
        }
    }
}
