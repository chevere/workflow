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
use Chevere\Parameter\Interfaces\BooleanParameterInterface;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersAccessInterface;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Interfaces\GraphInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\ReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use function Chevere\Action\getParameters;
use function Chevere\Message\message;
use function Chevere\Parameter\boolean;

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
     * @var MapInterface<ParameterInterface>
     */
    private MapInterface $variables;

    /**
     * @var MapInterface<ParameterInterface>
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
        $this->map = $this->map->withPut($name, $job);
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
        $action = $job->actionName()->__toString();
        // TODO: Support for mixed, void, ParameterAccessInterface
        if (! ($action::acceptResponse() instanceof ParametersAccessInterface)) {
            return;
        }
        foreach ($action::acceptResponse()->parameters() as $key => $parameter) {
            $this->references = $this->references
                ->withPut(
                    strval(reference($name, $key)),
                    $parameter,
                );
        }
    }

    private function handleArguments(string $name, JobInterface $job): void
    {
        foreach ($job->arguments() as $argument => $value) {
            $action = $job->actionName()->__toString();
            $parameter = getParameters($action)->get($argument);
            $property = match (true) {
                $value instanceof VariableInterface => 'variables',
                $value instanceof ReferenceInterface => 'references',
                default => false
            };
            if (! $property) {
                continue;
            }
            /** @var VariableInterface|ReferenceInterface $value */
            try {
                $this->mapParameter($name, $argument, $property, $parameter, $value);
            } catch (TypeError $e) {
                throw new TypeError(
                    message($e->getMessage())
                        ->withTranslate('%parameter%', $argument)
                        ->withTranslate('%job%', $name)
                );
            }
        }
    }

    private function mapParameter(
        string $job,
        string $argument,
        string $property,
        ParameterInterface $parameter,
        VariableInterface|ReferenceInterface $value,
    ): void {
        /** @var MapInterface<ParameterInterface> $map */
        $map = $this->{$property};
        $subject = 'Reference';
        $key = strval($value);
        if ($value instanceof VariableInterface) {
            $subject = 'Variable';
            $key = $value->__toString();
        }
        if (! $map->has($key)) {
            $map = $map->withPut($key, $parameter);
            $this->{$property} = $map;

            return;
        }
        /** @var ParameterInterface $stored */
        $stored = $map->get($key);
        if ($stored::class !== $parameter::class) {
            throw new TypeError(
                message('%subject% %key% is of type %type%, parameter %parameter% expects %expected% on job %job%.')
                    ->withCode('%type%', $stored->type()->primitive())
                    ->withCode('%expected%', $parameter->type()->primitive())
                    ->withTranslate('%subject%', $subject)
                    ->withTranslate('%key%', $key)
            );
        }

        try {
            $stored->assertCompatible($parameter);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                message('%subject% %key% conflict for parameter %parameter% on Job %job% (%message%).')
                    ->withCode('%subject%', $subject)
                    ->withCode('%key%', $key)
                    ->withCode('%parameter%', $argument)
                    ->withTranslate('%subject%', $subject)
                    ->withTranslate('%job%', $job)
                    ->withTranslate('%message%', $e->getMessage())
            );
        }
    }

    private function handleRunIfReference(mixed $runIf): void
    {
        if (! $runIf instanceof ReferenceInterface) {
            return;
        }
        $action = $this->get($runIf->job())->actionName()->__toString();
        $parameter = $action::acceptResponse()->parameters()->get($runIf->parameter());
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
                    $runIf->__toString(),
                    boolean(),
                );

            return;
        }
        /** @var ParameterInterface $parameter */
        $parameter = $this->variables->get($runIf->__toString());
        if (! ($parameter instanceof BooleanParameterInterface)) {
            throw new TypeError(
                message('Variable %variable% (previously declared as %type%) is not of type boolean at Job %job%')
                    ->withCode('%variable%', $runIf->__toString())
                    ->withCode('%type%', $parameter->type()->primitive())
                    ->withCode('%job%', $name)
            );
        }
    }

    private function assertDependencies(string $job): void
    {
        $dependencies = $this->jobDependencies->toArray();
        if (! $this->jobs->contains(...$dependencies)) {
            $missing = array_diff(
                $dependencies,
                $this->jobs->toArray()
            );

            throw new OutOfBoundsException(
                message('Job %job% has undeclared dependencies: %dependencies%')
                    ->withCode('%job%', $job)
                    ->withCode('%dependencies%', implode(', ', $missing))
            );
        }
    }
}
