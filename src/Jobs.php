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
use Chevere\Parameter\Interfaces\BoolParameterInterface;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersAccessInterface;
use Chevere\Workflow\Interfaces\GraphInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use InvalidArgumentException;
use OutOfBoundsException;
use OverflowException;
use Throwable;
use TypeError;
use function Chevere\Action\getParameters;
use function Chevere\Message\message;
use function Chevere\Parameter\bool;

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

    public function graph(): GraphInterface
    {
        return $this->graph;
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
                (string) message(
                    'Job name `%name%` has been already added.',
                    name: $name
                )
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

    private function storeReferences(string $job, JobInterface $item): void
    {
        $action = $item->action();
        $return = $action::return();
        if ($return instanceof ParametersAccessInterface) {
            foreach ($return->parameters() as $key => $parameter) {
                $this->references = $this->references
                    ->withPut(
                        strval(response($job, $key)),
                        $parameter,
                    );
            }
        } else {
            $this->references = $this->references
                ->withPut(
                    strval(response($job)),
                    $return,
                );
        }
    }

    private function handleArguments(string $job, JobInterface $item): void
    {
        foreach ($item->arguments() as $argument => $value) {
            $action = $item->action();
            $parameter = getParameters($action::class)->get($argument);
            $collection = match (true) {
                $value instanceof VariableInterface => 'variables',
                $value instanceof ResponseReferenceInterface => 'references',
                default => false
            };
            if (! $collection) {
                continue;
            }

            try {
                /** @var VariableInterface|ResponseReferenceInterface $value */
                $this->mapParameter($job, $argument, $collection, $parameter, $value);
            } catch (Throwable $e) {
                throw new $e(
                    strtr($e->getMessage(), [
                        '%parameter%' => $argument,
                        '%job%' => $job,
                    ])
                );
            }
        }
    }

    private function mapParameter(
        string $job,
        string $argument,
        string $collection,
        ParameterInterface $parameter,
        VariableInterface|ResponseReferenceInterface $value,
    ): void {
        /** @var MapInterface<ParameterInterface> $map */
        $map = $this->{$collection};
        $subject = 'Reference';
        $identifier = strval($value);
        if ($value instanceof VariableInterface) {
            $subject = 'Variable';
        } else {
            try {
                /** @var JobInterface $referenceJob */
                $referenceJob = $this->map->get($value->job());
                /** @var ParameterInterface $accept */
                $accept = $referenceJob->action()::return();
                if ($value->key() !== null) {
                    if (! $accept instanceof ParametersAccessInterface) {
                        throw new TypeError(
                            (string) message(
                                "Reference **%reference%** doesn't accept parameters",
                                reference: strval($value)
                            )
                        );
                    }
                    $accept->parameters()->get($value->key());
                }
            } catch (OutOfBoundsException) {
                throw new OutOfBoundsException(
                    (string) message(
                        '%subject% **%key%** not found at job **%job%**',
                        subject: $subject,
                        key: $identifier
                    )
                );
            }
        }
        if (! $map->has($identifier)) {
            $map = $map->withPut($identifier, $parameter);
            $this->{$collection} = $map;

            return;
        }
        /** @var ParameterInterface $stored */
        $stored = $map->get($identifier);
        if ($stored::class !== $parameter::class) {
            throw new TypeError(
                (string) message(
                    '%subject% **%key%** is of type `%type%`, parameter **%parameter%** expects `%expected%` at job **%job%**',
                    type: $stored->type()->primitive(),
                    expected: $parameter->type()->primitive(),
                    subject: $subject,
                    key: $identifier
                )
            );
        }

        try {
            $stored->assertCompatible($parameter);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(
                (string) message(
                    '%subject% **%key%** conflict for parameter **%parameter%** on job **%job%** (%message%).',
                    subject: $subject,
                    key: $identifier,
                    parameter: $argument,
                    job: $job,
                    message: $e->getMessage()
                )
            );
        }
    }

    private function handleRunIfReference(mixed $runIf): void
    {
        if (! $runIf instanceof ResponseReferenceInterface) {
            return;
        }
        $action = $this->get($runIf->job())->action();
        $accept = $action::return();
        if ($runIf->key() !== null) {
            if (! $accept instanceof ParametersAccessInterface) {
                throw new TypeError(
                    (string) message(
                        'Reference **%reference%** doesn\'t accept parameters',
                        reference: strval($runIf)
                    )
                );
            }
            $accept = $accept->parameters()->get($runIf->key());
        }
        if ($accept->type()->primitive() === 'bool') {
            return;
        }

        throw new TypeError(
            (string) message(
                'Reference **%reference%** must be of type `bool`',
                reference: strval($runIf)
            )
        );
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
                    bool(),
                );

            return;
        }
        /** @var ParameterInterface $parameter */
        $parameter = $this->variables->get($runIf->__toString());
        if (! ($parameter instanceof BoolParameterInterface)) {
            throw new TypeError(
                (string) message(
                    'Variable **%variable%** (previously declared as `%type%`) is not of type `bool` at Job **%job%**',
                    variable: $runIf->__toString(),
                    type: $parameter->type()->primitive(),
                    job: $name,
                )
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
                (string) message(
                    'Job **%job%** has undeclared dependencies: `%dependencies%`',
                    job: $job,
                    dependencies: implode(', ', $missing),
                )
            );
        }
    }
}
