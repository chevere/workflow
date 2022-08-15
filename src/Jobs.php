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
use Ds\Map as DsMap;
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

    private DsMap $variables;

    private DsMap $references;

    public function __construct(JobInterface ...$jobs)
    {
        $this->map = new Map();
        $this->jobs = new Vector();
        $this->graph = new Graph();
        $this->variables = new DsMap();
        $this->references = new DsMap();
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

    public function variables(): array
    {
        return $this->variables->toArray();
    }

    public function references(): array
    {
        return $this->references->toArray();
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
            $name = strval($name);
            $this->addMap($name, $job);
            $this->jobs->push($name);
            $this->handleArguments($name, $job);
            $this->handleRunIf($name, $job);
            $this->storeReferences($name, $job);
            $this->graph = $this->graph->withPut($name, $job);
        }
    }

    private function storeReferences(string $name, JobInterface $job): void
    {
        /** @var ActionInterface $action */
        $action = new ($job->action());
        foreach ($action->responseParameters()->getIterator() as $key => $parameter) {
            $this->references->put(strval(reference($name, $key)), $parameter->type());
        }
    }

    private function handleArguments(string $name, JobInterface $job): void
    {
        foreach ($job->arguments() as $parameter => $argument) {
            /** @var ActionInterface $action */
            $action = new ($job->action());
            /** @var TypeInterface $type */
            $type = $action->parameters()->get($parameter)->type();

            try {
                if ($argument instanceof VariableInterface
                    || $argument instanceof ReferenceInterface) {
                    $subjectArguments = $argument instanceof VariableInterface
                        ? ['Variable', $this->variables, $argument->name(), $type]
                        : ['Reference', $this->references, strval($argument), $type];
                    $this->handleSubjectType(...$subjectArguments);
                }
            } catch (TypeError $e) {
                throw new TypeError(
                    message($e->getMessage())
                        ->withStrtr('%parameter%', $parameter)
                        ->withStrtr('%job%', $name)
                );
            }
        }
    }

    private function handleSubjectType(string $subject, DsMap $map, string $key, TypeInterface $type): void
    {
        if ($map->hasKey($key)) {
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
        } else {
            $map->put($key, $type);
        }
    }

    private function handleRunIf(
        string $name,
        JobInterface $job
    ): void {
        $dependencies = $job->dependencies();
        foreach ($job->runIf() as $runIf) {
            if ($runIf instanceof ReferenceInterface) {
                $dependencies[] = $runIf->job();
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
            $this->handleRunIfVariable($name, $runIf);
        }
        $dependencies = array_filter($dependencies);
        $this->assertDependencies($name, ...$dependencies);
    }

    private function handleRunIfVariable(string $job, $runIf): void
    {
        if (!$runIf instanceof VariableInterface) {
            return;
        }
        if (!$this->variables->hasKey($runIf->name())) {
            $this->variables->put($runIf->name(), typeBoolean());

            return;
        }
        /** @var TypeInterface $type */
        $type = $this->variables->get($runIf->name());
        if ($type->primitive() !== 'boolean') {
            throw new TypeError(
                message('Variable %variable% (previously declared as %type%) is not of type boolean at job %job%')
                    ->withCode('%variable%', $runIf->name())
                    ->withCode('%type%', $type->primitive())
                    ->withCode('%job%', $job)
            );
        }
    }

    private function assertDependencies(string $job, string ...$dependencies): void
    {
        if (!$this->jobs->contains(...$dependencies)) {
            $missing = array_diff($dependencies, $this->jobs->toArray());

            throw new OutOfBoundsException(
                message('Job %job% has undeclared dependencies: %dependencies%')
                    ->withCode('%job%', $job)
                    ->withCode(
                        '%dependencies%',
                        implode(', ', $missing)
                    )
            );
        }
    }
}
