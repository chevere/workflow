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
use Chevere\Parameter\Interfaces\MixedParameterInterface;
use Chevere\Parameter\Interfaces\ObjectParameterInterface;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersAccessInterface;
use Chevere\Parameter\Interfaces\UnionParameterInterface;
use Chevere\Workflow\Exceptions\JobsException;
use Chevere\Workflow\Interfaces\GraphInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;
use OverflowException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Throwable;
use TypeError;
use function Chevere\Parameter\bool;
use function Chevere\Parameter\reflectionToParameter;

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
     * @var Map<ParameterInterface>
     */
    private MapInterface $variables;

    /**
     * @var Map<ParameterInterface>
     */
    private MapInterface $references;

    /**
     * @var VectorInterface<string>
     */
    private VectorInterface $jobDependencies;

    private Config $config;

    private VectorInterface $violations;

    public function __construct(JobInterface ...$jobs)
    {
        $this->config = Config::fromEnv();
        $this->violations = new Vector();
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

    public function violations(): VectorInterface
    {
        return $this->violations;
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
                sprintf(
                    'Job name `%s` has been already added.',
                    $name
                )
            );
        }
        $this->map = $this->map->withPut($name, $job);
    }

    private function putAdded(JobInterface ...$job): void
    {
        foreach ($job as $name => $item) {
            try {
                $this->jobDependencies = $item->dependencies();
                $name = strval($name);
                $this->addMap($name, $item);
                $this->jobs = $this->jobs->withPush($name);
                $this->handleArguments($name, $item);
                foreach ($item->runIf() as $runIf) {
                    $this->handleRunIfReference($name, $runIf, 'withRunIf');
                    $this->handleRunIfVariable($name, $runIf, 'withRunIf');
                }
                foreach ($item->runIfNot() as $runIfNot) {
                    $this->handleRunIfReference($name, $runIfNot, 'withRunIfNot');
                    $this->handleRunIfVariable($name, $runIfNot, 'withRunIfNot');
                }
                $this->storeReferences($name, $item);
                $this->assertDependencies($name);
                $this->graph = $this->graph->withPut($name, $item);
            } catch (Throwable $e) {
                if (! $this->config->isLint) {
                    throw $e;
                }
                $this->violations = $this->violations->withPush(
                    [
                        'job' => $name,
                        'message' => $e->getMessage(),
                    ]
                );
            }
        }
    }

    private function storeReferences(string $job, JobInterface $item): void
    {
        $return = $item->return();
        $this->references = $this->references
            ->withPut(
                strval(response($job)),
                $return,
            );
        if ($return instanceof ObjectParameterInterface) {
            $properties = (new ReflectionClass($return->className()))->getProperties(ReflectionProperty::IS_PUBLIC);
            foreach ($properties as $property) {
                $this->references = $this->references
                    ->withPut(
                        strval(response($job, $property->getName())),
                        reflectionToParameter($property),
                    );
            }
        }
        if ($return instanceof ParametersAccessInterface && ! ($return instanceof UnionParameterInterface)) {
            foreach ($return->parameters() as $key => $parameter) {
                $this->references = $this->references
                    ->withPut(
                        strval(response($job, $key)),
                        $parameter,
                    );
            }
        }
    }

    private function handleArguments(string $name, JobInterface $job): void
    {
        foreach ($job->arguments() as $argument => $value) {
            $argument = strval($argument);
            $parameters = $job->parameters();
            $parameter = null;
            if ($parameters->has($argument)) {
                $parameter = $parameters->get($argument);
            } else {
                if (ctype_digit($argument)) {
                    $keys = $parameters->keys();
                    $index = intval($argument);
                    if (array_key_exists($index, $keys)) {
                        $argument = $keys[$index];
                        $parameter = $parameters->get($argument);
                    }
                }
                if ($parameter === null && $parameters->isVariadic()) {
                    /** @var string|int $lastKey */
                    $lastKey = array_key_last($parameters->keys());
                    $lastName = $parameters->keys()[$lastKey];
                    $argument = $lastName;
                    $parameter = $parameters->get($lastName);
                }
            }
            $collection = match (true) {
                $value instanceof VariableInterface => 'variables',
                $value instanceof ResponseReferenceInterface => 'references',
                default => false
            };
            if (! $collection || $parameter === null) {
                continue;
            }

            try {
                /** @var VariableInterface|ResponseReferenceInterface $value */
                $this->mapParameter($argument, $collection, $parameter, $value);
            } catch (Throwable $e) {
                if (! $this->config->isLint) {
                    throw new JobsException(name: $name, job: $job, throwable: $e);
                }
                $this->violations = $this->violations->withPush(
                    [
                        'job' => $name,
                        'parameter' => $argument,
                        'message' => str_replace(
                            "Argument [{$argument}]: ",
                            '',
                            $e->getMessage(),
                        ),
                    ]
                );
            }
        }
    }

    private function mapParameter(
        string $argument,
        string $collection,
        ParameterInterface $parameter,
        VariableInterface|ResponseReferenceInterface $value,
    ): void {
        /** @var MapInterface<ParameterInterface> $map */
        $map = $this->{$collection};
        $subject = 'Response';
        $identifier = strval($value);
        if ($value instanceof VariableInterface) {
            $subject = 'Variable';
        } else {
            try {
                /** @var JobInterface $responseJob */
                $responseJob = $this->map->get($value->job());
                /** @var ParameterInterface $accept */
                $accept = $responseJob->return();
                if ($value->key() !== null) {
                    $found = false;
                    if ($accept instanceof ObjectParameterInterface) {
                        /** @var class-string $className */
                        $className = $accept->className();

                        try {
                            $reflection = (new ReflectionClass($className))->getProperty($value->key());
                        } catch (ReflectionException) {
                            throw new LogicException(
                                sprintf(
                                    "Invalid response reference **%s** as job **%s** doesn't define such property",
                                    strval($value),
                                    $value->job()
                                )
                            );
                        }
                        if (! $reflection->isPublic()) {
                            throw new LogicException(
                                sprintf(
                                    'Response **%s** job `%s` property `%s` is not public',
                                    strval($value),
                                    $value->job(),
                                    $value->key()
                                )
                            );
                        }
                        $found = true;
                    }
                    if ($accept instanceof ParametersAccessInterface && $accept->parameters()->has($value->key())) {
                        $found = true;
                    }
                    if (! $found) {
                        throw new LogicException(
                            sprintf(
                                "Invalid response reference **%s** as job **%s** doesn't define such response key",
                                strval($value),
                                $value->job()
                            )
                        );
                    }
                }
            } catch (OutOfBoundsException) {
                throw new OutOfBoundsException(
                    sprintf(
                        '%s **%s** not found',
                        $subject,
                        $identifier
                    )
                );
            }
        }
        if ($map->has($identifier)) {
            /** @var ParameterInterface $stored */
            $stored = $map->get($identifier);
            if ($parameter instanceof UnionParameterInterface) {
                $errors = [];
                $count = count($parameter->parameters());
                if ($stored instanceof UnionParameterInterface) {
                    $found = false;
                    foreach ($parameter->parameters() as $tryParameter) {
                        $matched = false;
                        foreach ($stored->parameters() as $storedParam) {
                            try {
                                $storedParam->assertCompatible($tryParameter);
                                $stored = $storedParam;
                                $parameter = $tryParameter;
                                $found = true;
                                $matched = true;

                                break;
                            } catch (TypeError $e) {
                            }
                        }
                        if (! $matched) {
                            $errors[] = $tryParameter->type()->typeHinting();
                        } else {
                            break;
                        }
                    }
                    if (! $found) {
                        throw new TypeError(
                            sprintf(
                                '%s **%s** is of type `%s`, parameter **%s** expects one of: %s',
                                $subject,
                                $identifier,
                                $stored->type()
                                    ->typeHinting(),
                                $argument,
                                '`' . implode('`, `', $errors) . '`'
                            )
                        );
                    }
                } else {
                    foreach ($parameter->parameters() as $tryParameter) {
                        try {
                            $stored->assertCompatible($tryParameter);
                            $parameter = $tryParameter;
                        } catch (TypeError $e) {
                            $errors[] = $tryParameter->type()->typeHinting();
                        }
                    }
                    if (count($errors) === $count) {
                        throw new TypeError(
                            sprintf(
                                '%s from **%s** is of type `%s`, parameter **%s** expects one of: %s',
                                $subject,
                                $identifier,
                                $stored->type()
                                    ->primitive(),
                                $argument,
                                '`' . implode('`, `', $errors) . '`'
                            )
                        );
                    }
                }
            }
            if ($stored instanceof MixedParameterInterface
                || $parameter instanceof MixedParameterInterface
            ) {
                return; // @codeCoverageIgnore
            }
            if ($stored::class !== $parameter::class) {
                throw new TypeError(
                    sprintf(
                        '%s **%s** is of type `%s`, parameter **%s** expects `%s`',
                        $subject,
                        $identifier,
                        $stored->type()
                            ->primitive(),
                        $argument,
                        $parameter->type()
                            ->primitive()
                    )
                );
            }

            try {
                $parameter->assertCompatible($stored);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException(
                    sprintf(
                        '%s **%s** conflict at parameter **%s**: %s',
                        $subject,
                        $identifier,
                        $argument,
                        $e->getMessage()
                    )
                );
            }
        } else {
            $map = $map->withPut($identifier, $parameter);
            $this->{$collection} = $map;
        }
    }

    private function handleRunIfReference(string $name, mixed $runIf, string $method): void
    {
        if (! $runIf instanceof ResponseReferenceInterface) {
            return;
        }

        try {
            $return = $this->map->get($runIf->job())
                ->return();
            if ($runIf->key() !== null) {
                if (! $return instanceof ParametersAccessInterface) {
                    throw new OutOfBoundsException(
                        sprintf(
                            'Response **%s** job `%s` doesn\'t bind to `%s` parameter',
                            strval($runIf),
                            $runIf->job(),
                            $runIf->key()
                        )
                    );
                }
                $return = $return->parameters()
                    ->get($runIf->key());
            }
        } catch (Throwable $e) {
            if (! $this->config->isLint) {
                throw $e;
            }
            $this->violations = $this->violations->withPush(
                [
                    'job' => $name,
                    'method' => $method,
                    'response' => strval($runIf),
                    'message' => $e->getMessage(),
                ]
            );

            return;
        }
        if (in_array($return->type()->primitive(), ['bool', 'int'], true)) {
            return;
        }
        $message = sprintf(
            'Response **%s** must be of type `bool|int`, type `%s` provided',
            strval($runIf),
            $return->type()
                ->primitive()
        );
        if (! $this->config->isLint) {
            throw new TypeError($message);
        }
        $this->violations = $this->violations->withPush(
            [
                'job' => $name,
                'method' => $method,
                'response' => $runIf->__toString(),
                'message' => $message,
            ]
        );
    }

    private function handleRunIfVariable(string $name, mixed $runIf, string $method): void
    {
        if (! $runIf instanceof VariableInterface) {
            return;
        }
        if ($this->variables->has($runIf->__toString())) {
            /** @var ParameterInterface $parameter */
            $parameter = $this->variables->get($runIf->__toString());
            if (! in_array($parameter->type()->primitive(), ['bool', 'int'], true)) {
                $message = sprintf(
                    'Variable **%s** (previously inferred as `%s`) is not of type `bool|int` at Job **%s**',
                    $runIf->__toString(),
                    $parameter->type()
                        ->primitive(),
                    $name
                );
                if (! $this->config->isLint) {
                    throw new TypeError($message);
                }
                $this->violations = $this->violations->withPush(
                    [
                        'job' => $name,
                        'method' => $method,
                        'variable' => $runIf->__toString(),
                        'message' => $message,
                    ]
                );
            }
        } else {
            $this->variables = $this->variables
                ->withPut(
                    $runIf->__toString(),
                    bool(),
                );
        }
    }

    private function assertDependencies(string $job): void
    {
        $dependencies = $this->jobDependencies->toArray();
        if (! $this->jobs->contains(...$dependencies)) {
            $missing = array_diff($dependencies, $this->jobs->toArray());
            $message = sprintf(
                'Job **%s** has undeclared dependencies: `%s`',
                $job,
                implode(', ', $missing),
            );
            if (! $this->config->isLint) {
                throw new OutOfBoundsException($message);
            }
            $this->violations = $this->violations->withPush(
                [
                    'job' => $job,
                    'method' => 'withDepends',
                    'missing' => $missing,
                    'message' => $message,
                ]
            );
        }
    }
}
