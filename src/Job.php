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
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\DataStructure\Vector;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Workflow\Interfaces\CallerInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use InvalidArgumentException;
use OverflowException;
use function Chevere\Action\getParameters;
use function Chevere\Message\message;
use function Chevere\Parameter\assertNamedArgument;

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

    /**
     * @var VectorInterface<ResponseReferenceInterface|VariableInterface>
     */
    private VectorInterface $runIf;

    private bool $isSync;

    private CallerInterface $caller;

    /**
     * Creates a Job
     * DO NOT use this method directly, use `sync` or `async` functions instead.
     *
     * @param ActionInterface $_ The action to run
     * @param mixed ...$argument Action arguments for its run method (raw, reference or variable)
     */
    public function __construct(
        private ActionInterface $_,
        mixed ...$argument
    ) {
        $this->isSync = false;
        $this->runIf = new Vector();
        $this->dependencies = new Vector();
        $this->parameters = getParameters($_::class);
        $this->arguments = [];
        $this->setArguments(...$argument);
        $debugBacktrace = debug_backtrace(options: 0, limit: 2);
        $callerFunction = $debugBacktrace[1]['function'] ?? '';
        $index = (int) in_array(
            $callerFunction,
            ['Chevere\Workflow\sync', 'Chevere\Workflow\async']
        );
        $debugBacktrace = $debugBacktrace[$index];
        $file = $debugBacktrace['file'] ?? 'unknown';
        $line = $debugBacktrace['line'] ?? 0;
        $this->caller = new Caller($file, (int) $line);
    }

    public function caller(): CallerInterface
    {
        return $this->caller;
    }

    public function withArguments(mixed ...$argument): JobInterface
    {
        $new = clone $this;
        $new->setArguments(...$argument);

        return $new;
    }

    public function withRunIf(ResponseReferenceInterface|VariableInterface ...$context): JobInterface
    {
        $new = clone $this;
        $new->runIf = new Vector();
        $known = new Vector();
        foreach ($context as $item) {
            if ($known->contains($item->__toString())) {
                throw new OverflowException(
                    (string) message(
                        'Condition `%condition%` is already defined',
                        condition: $item->__toString()
                    )
                );
            }
            $new->inferDependencies($item);
            $new->runIf = $new->runIf->withPush($item);
            $known = $known->withPush($item->__toString());
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

    public function action(): ActionInterface
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
        $lastKey = array_key_last($this->parameters->keys());
        $lastName = $this->parameters->keys()[$lastKey] ?? null;
        $values = [];
        foreach ($this->parameters as $name => $parameter) {
            if ($name === $lastName && $this->parameters->isVariadic()) {
                $variadicKeys = array_diff_key(
                    $argument,
                    array_flip($this->parameters->keys())
                );
                foreach ($variadicKeys as $key => $value) {
                    $key = strval($key);
                    $values[$key] = $value;
                    $this->inferDependencies($value);
                    $this->assertParameter($name, $parameter, $value);
                }

                break;
            }

            if (array_key_exists($name, $argument)) {
                $value = $argument[$name];
                $values[$name] = $value;
                $this->inferDependencies($value);
                $this->assertParameter($name, $parameter, $value);
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
        $intersectKeys = array_intersect(array_keys($arguments), $requiredKeys);
        $countIntersect = count($intersectKeys);
        $missing = array_map(
            fn (string $item) => $this->formatAsVariable($item),
            array_diff($requiredKeys, $intersectKeys)
        );
        if ($missing !== []) {
            throw new ArgumentCountError(
                (string) message(
                    'Missing argument(s) [`%arguments%`] for `%action%`',
                    arguments: implode(', ', $missing),
                    action: $this->_::class
                )
            );
        }
        if (count($requiredKeys) > $countProvided
            || count($requiredKeys) !== $countIntersect
            || $countProvided > count($this->parameters)
        ) {
            $requiredVars = array_map(
                fn (string $item) => $this->formatAsVariable($item),
                $requiredKeys
            );
            $parameters = implode(', ', $requiredVars);
            $parameters = $parameters === '' ? '' : "[{$parameters}]";

            throw new ArgumentCountError(
                (string) message(
                    '`%symbol%` requires %countRequired% argument(s)%parameters%',
                    symbol: $this->_::class . '::' . $this->_::mainMethod(),
                    countRequired: strval(count($requiredKeys)),
                    parameters: $parameters === '' ? '' : " `{$parameters}`"
                )
            );
        }
    }

    private function formatAsVariable(string $name): string
    {
        return $this->parameters->get($name)->type()->typeHinting()
            . " \${$name}";
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
        $this->dependencies = $this->dependencies
            ->withPush($argument->job());
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
