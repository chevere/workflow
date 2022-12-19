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
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\DataStructure\Vector;
use function Chevere\Message\message;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\String\AssertString;
use Chevere\Throwable\Errors\ArgumentCountError;
use Chevere\Throwable\Exceptions\BadMethodCallException;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Throwable\Exceptions\UnexpectedValueException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ReferenceInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use ReflectionClass;
use ReflectionException;

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

    private bool $isSync = false;

    /**
     * @var VectorInterface<ReferenceInterface|VariableInterface>
     */
    private VectorInterface $runIf;

    public function __construct(
        private string $action,
        mixed ...$actionParameter
    ) {
        $this->runIf = new Vector();

        try {
            // @phpstan-ignore-next-line
            $reflection = new ReflectionClass($this->action);
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException(
                message("Class %action% doesn't exists")
                    ->withCode('%action%', $this->action)
            );
        }
        if (! $reflection->implementsInterface(ActionInterface::class)) {
            throw new UnexpectedValueException(
                message('Action %action% must implement %interface% interface')
                    ->withCode('%action%', $this->action)
                    ->withCode('%interface%', ActionInterface::class)
            );
        }
        $this->dependencies = new Vector();
        /** @var ActionInterface $instance */
        $instance = $reflection->newInstance();
        $this->parameters = $instance->parameters();
        $this->arguments = [];
        $this->setArguments(...$actionParameter);
    }

    public function withArguments(mixed ...$namedArguments): JobInterface
    {
        $new = clone $this;
        $new->setArguments(...$namedArguments);

        return $new;
    }

    public function withRunIf(ReferenceInterface|VariableInterface ...$context): JobInterface
    {
        $new = clone $this;
        $new->runIf = new Vector();
        $known = new Vector();
        foreach ($context as $item) {
            if ($known->contains($item->__toString())) {
                throw new OverflowException(
                    message('Condition %condition% is already defined')
                        ->withCode('%condition%', $item->__toString())
                );
            }
            $new->inferDependencies($item);
            $new->runIf = $new->runIf->withPush($item);
            $known = $known->withPush($item->__toString());
        }

        return $new;
    }

    public function withIsSync(): JobInterface
    {
        $new = clone $this;
        $new->isSync = true;

        return $new;
    }

    public function withDepends(string ...$jobs): JobInterface
    {
        $new = clone $this;
        $new->addDependencies(...$jobs);

        return $new;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function getAction(): ActionInterface
    {
        /** @var ActionInterface */
        return new $this->action();
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
        /** @var array<string, mixed> $argument */
        $this->assertArgumentsCount($argument);
        $values = [];
        $missing = [];
        foreach ($this->parameters as $name => $parameter) {
            $item = $argument[$name] ?? null;
            if ($item !== null) {
                $values[$name] = $item;
                $this->inferDependencies($item);
            } elseif ($this->parameters->isRequired($name)) {
                $missing[] = $parameter->type()->typeHinting()
                    . " {$name}";
            }
        }
        if ($missing !== []) {
            throw new BadMethodCallException(
                message('Missing argument(s) [%arguments%] for %action%')
                    ->withCode('%arguments%', implode(', ', $missing))
                    ->withCode('%action%', $this->action)
            );
        }
        $this->arguments = $values;
    }

    /**
     * @param mixed[] $arguments
     */
    private function assertArgumentsCount(array $arguments): void
    {
        $countProvided = count($arguments);
        $countRequired = count($this->parameters->required());
        if ($countRequired > $countProvided
            || $countRequired !== $countProvided
        ) {
            $provided = implode(', ', array_keys($arguments));
            $parameters = implode(
                ', ',
                $this->parameters->required()
            );

            throw new ArgumentCountError(
                message('Method %method% of %action% requires %countRequired% arguments %parameters% (provided %countProvided% %provided%)')
                    ->withStrong('%method%', 'run')
                    ->withStrong('%action%', $this->action)
                    ->withCode('%countRequired%', strval($countRequired))
                    ->withCode('%provided%', $provided)
                    ->withCode('%countProvided%', strval($countProvided))
                    ->withCode('%parameters%', $parameters)
            );
        }
    }

    private function inferDependencies(mixed $argument): void
    {
        if (! ($argument instanceof ReferenceInterface)) {
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
                message('Job dependencies must be unique (repeated %dependencies%)')
                    ->withCode(
                        '%dependencies%',
                        implode(', ', array_diff_assoc($dependencies, $uniques))
                    )
            );
        }
        foreach ($dependencies as $dependency) {
            (new AssertString($dependency))
                ->notEmpty()
                ->notCtypeDigit()
                ->notCtypeSpace();
        }
    }
}
