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

use ArgumentCountError;
use Chevere\Action\Interfaces\ActionInterface;
use Chevere\Caller\Caller;
use Chevere\Caller\Interfaces\CallerInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\DataStructure\Vector;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\RetryPolicyInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Closure;
use InvalidArgumentException;
use OverflowException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use function Chevere\Message\message;
use function Chevere\Parameter\assertNamedArgument;
use function Chevere\Parameter\reflectionToParameters;
use function Chevere\Parameter\reflectionToReturn;

final class Job implements JobInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $arguments;

    /**
     * @var VectorInterface<string>
     */
    private VectorInterface $dependencies;

    private ParametersInterface $parameters;

    private ParameterInterface $return;

    /**
     * @var VectorInterface<ResponseReferenceInterface|VariableInterface>
     */
    private VectorInterface $runIf;

    private bool $isSync;

    private CallerInterface $caller;

    private RetryPolicyInterface $retryPolicy;

    /**
     * Creates a Job
     * DO NOT use this method directly, use `sync` or `async` functions instead.
     *
     * @param ActionInterface|class-string<ActionInterface>|Closure $_ The action to run
     * @param mixed ...$argument Action arguments for its run method (raw, reference or variable)
     */
    public function __construct(
        private ActionInterface|string|Closure $_,
        mixed ...$argument
    ) {
        $debugBacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callerFunction = $debugBacktrace[1]['function'] ?? '';
        $index = in_array(
            $callerFunction,
            ['Chevere\Workflow\sync', 'Chevere\Workflow\async']
        );
        $callerTrace = $debugBacktrace[intval($index)];
        $file = $callerTrace['file'] ?? 'unknown';
        $line = $callerTrace['line'] ?? 0;
        $this->caller = new Caller($file, $line);
        $this->isSync = false;
        $this->runIf = new Vector();
        $this->dependencies = new Vector();
        if ($_ instanceof Closure) {
            $reflection = new ReflectionMethod($_, '__invoke');
            $this->parameters = reflectionToParameters($reflection);
            $this->return = reflectionToReturn($reflection);
        } else {
            $this->parameters = $_::reflection()->parameters();
            $this->return = $_::reflection()->return();
        }
        $this->arguments = [];
        $this->setArguments(...$argument);
        $this->retryPolicy = new RetryPolicy();
    }

    public function caller(): CallerInterface
    {
        return $this->caller;
    }

    public function parameters(): ParametersInterface
    {
        return $this->parameters;
    }

    public function return(): ParameterInterface
    {
        return $this->return;
    }

    public function retryPolicy(): RetryPolicyInterface
    {
        return $this->retryPolicy;
    }

    public function withArguments(mixed ...$argument): JobInterface
    {
        $new = clone $this;
        $new->setArguments(...$argument);

        return $new;
    }

    public function withRunIf(ResponseReferenceInterface|VariableInterface|callable|bool ...$context): JobInterface
    {
        $new = clone $this;
        $new->runIf = new Vector();
        $known = new Vector();
        foreach ($context as $item) {
            $itemString = match (true) {
                $item instanceof ResponseReferenceInterface,
                $item instanceof VariableInterface => $item->__toString(),
                $item instanceof Closure => 'callable#' . spl_object_id($item),
                default => $item === true ? 'bool#true' : 'bool#false',
            };
            if ($known->contains($itemString)) {
                throw new OverflowException(
                    (string) message(
                        'Condition `%condition%` is already defined',
                        condition: $itemString
                    )
                );
            }
            $new->inferDependencies($item);
            $new->runIf = $new->runIf->withPush($item);
            $known = $known->withPush($itemString);
        }

        return $new;
    }

    public function withIsSync(bool $flag = true): JobInterface
    {
        $new = clone $this;
        $new->isSync = $flag;

        return $new;
    }

    public function withDepends(string ...$jobs): JobInterface
    {
        $new = clone $this;
        $new->addDependencies(...$jobs);

        return $new;
    }

    public function withRetry(
        int $timeout = 0,
        int $maxAttempts = 1,
        int $delay = 0
    ): JobInterface {
        $new = clone $this;
        $new->retryPolicy = new RetryPolicy($timeout, $maxAttempts, $delay);

        return $new;
    }

    public function action(): ActionInterface|string|Closure
    {
        return $this->_;
    }

    public function arguments(): array
    {
        return $this->arguments;
    }

    public function dependencies(): VectorInterface
    {
        return $this->dependencies;
    }

    public function runIf(): VectorInterface
    {
        return $this->runIf;
    }

    public function isSync(): bool
    {
        return $this->isSync;
    }

    private function setArguments(mixed ...$argument): void
    {
        if (! $this->parameters->isVariadic()) {
            $this->assertArgumentsCount($argument);
        }
        $values = [];
        $isPositional = array_is_list($argument);
        $lastKey = array_key_last($this->parameters->keys());
        $lastName = $this->parameters->keys()[$lastKey] ?? null;
        foreach ($this->parameters as $name => $parameter) {
            if ($name === $lastName && $this->parameters->isVariadic()) {
                if ($isPositional) {
                    $sliceAt = count($this->parameters) - 1;
                    $variadicKeys = array_slice($argument, $sliceAt);
                    if ($variadicKeys === []) {
                        continue;
                    }
                    $variadicKeys = array_combine(
                        range($sliceAt, $sliceAt + count($variadicKeys) - 1),
                        $variadicKeys
                    );
                } else {
                    $variadicKeys = array_diff_key(
                        $argument,
                        array_flip($this->parameters->keys())
                    );
                }
                foreach ($variadicKeys as $key => $value) {
                    $key = strval($key);
                    $values[$key] = $value;
                    $this->inferDependencies($value);
                    $this->assertParameter($name, $parameter, $value);
                }

                break;
            }
            if (! array_key_exists($name, $argument)) {
                $named = strval($name);
                $name = array_search($name, $this->parameters->keys());
                if ($name === false) {
                    continue;
                }
                $name = strval($name);
            }
            if (array_key_exists($name, $argument)) {
                $value = $argument[$name];
                $values[$name] = $value;
                $this->inferDependencies($value);
                $this->assertParameter($named ?? $name, $parameter, $value);
            }
        }
        $this->arguments = $values;
    }

    /**
     * @param mixed[] $arguments
     */
    private function assertArgumentsCount(array $arguments): void
    {
        $countProvided = count($arguments);
        $requiredKeys = $this->parameters->requiredKeys()->toArray();
        $isPositional = array_is_list($arguments);
        if ($isPositional) {
            $countRequired = count($requiredKeys);
            if ($countProvided < $countRequired) {
                $missing = array_map(
                    $this->formatAsVariable(...),
                    array_slice($requiredKeys, $countProvided)
                );
            } else {
                $missing = [];
            }
        } else {
            $intersectKeys = array_intersect(array_keys($arguments), $requiredKeys);
            $missing = array_map(
                $this->formatAsVariable(...),
                array_diff($requiredKeys, $intersectKeys)
            );
        }
        if ($missing !== []) {
            if ($this->_ instanceof Closure) {
                $reflection = new ReflectionFunction($this->_);
                $fileName = $reflection->getFileName();
                $startLine = $reflection->getStartLine();
                $class = "closure @ {$fileName}:{$startLine}";
            } else {
                $reflection = new ReflectionClass($this->_);
                $class = "`{$reflection->getName()}`";
                if ($reflection->isAnonymous()) {
                    $fileName = $reflection->getFileName();
                    $startLine = $reflection->getStartLine();
                    $class = "anon class @ {$fileName}:{$startLine}";
                }
            }

            throw new ArgumentCountError(
                (string) message(
                    'Missing argument(s) [`%arguments%`] for %action% in %fileLine%',
                    arguments: implode(', ', $missing),
                    action: $class,
                    fileLine: $this->caller->__toString()
                )
            );
        }
        if (! $isPositional) {
            $intersectKeys = array_intersect(array_keys($arguments), $requiredKeys);
            $countIntersect = count($intersectKeys);
            if (count($requiredKeys) > $countProvided
                || count($requiredKeys) !== $countIntersect
                || $countProvided > count($this->parameters)
            ) {
                $requiredVars = array_map(
                    $this->formatAsVariable(...),
                    $requiredKeys
                );
                $parameters = implode(', ', $requiredVars);
                $parameters = $parameters === '' ? '' : "[{$parameters}]";

                throw new ArgumentCountError(
                    (string) message(
                        '`%symbol%` requires %countRequired% argument(s)%parameters%',
                        symbol: $this->_::class . '::__invoke',
                        countRequired: strval(count($requiredKeys)),
                        parameters: $parameters === '' ? '' : " `{$parameters}`"
                    )
                );
            }
        } elseif ($countProvided > count($this->parameters)) {
            throw new ArgumentCountError(
                (string) message(
                    '`%symbol%` requires %countRequired% argument(s), but %countProvided% provided',
                    symbol: $this->_::class . '::__invoke',
                    countRequired: strval(count($this->parameters)),
                    countProvided: strval($countProvided)
                )
            );
        }
    }

    private function formatAsVariable(string $name): string
    {
        return $this->parameters->get($name)->type()->typeHinting() . " \${$name}";
    }

    private function assertParameter(string $name, ParameterInterface $parameter, mixed $value): void
    {
        if ($value instanceof ResponseReferenceInterface || $value instanceof VariableInterface) {
            return;
        }
        assertNamedArgument($name, $parameter, $value);
    }

    private function inferDependencies(mixed $argument): void
    {
        $condition = $argument instanceof ResponseReferenceInterface;
        if (! $condition) {
            return;
        }
        if ($this->dependencies->contains($argument->job())) {
            return;
        }
        $this->dependencies = $this->dependencies->withPush($argument->job());
    }

    private function addDependencies(string ...$jobs): void
    {
        $this->assertDependencies(...$jobs);
        foreach ($jobs as $job) {
            if ($this->dependencies->contains($job)) {
                continue;
            }
            $this->dependencies = $this->dependencies->withPush($job);
        }
    }

    private function assertDependencies(string ...$dependencies): void
    {
        $uniques = array_unique($dependencies);
        if ($uniques !== $dependencies) {
            throw new OverflowException(
                (string) message(
                    'Job dependencies must be unique (repeated **%dependencies%**)',
                    dependencies: implode(', ', array_diff_assoc($dependencies, $uniques))
                )
            );
        }
        foreach ($dependencies as $dependency) {
            if (empty($dependency) || ctype_digit($dependency) || ctype_space($dependency)) {
                throw new InvalidArgumentException();
            }
        }
    }
}
