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
use Ds\Vector;
use ReflectionClass;
use ReflectionException;

final class Job implements JobInterface
{
    /**
     * @var Array<string, mixed>
     */
    private array $arguments;

    /**
     * @var Vector<string>
     */
    private Vector $dependencies;

    private ParametersInterface $parameters;

    private bool $isSync = false;

    /**
     * @var Vector<ReferenceInterface|VariableInterface>
     */
    private Vector $runIf;

    public function __construct(
        private string $action,
        mixed ...$namedArguments
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
        if (!$reflection->implementsInterface(ActionInterface::class)) {
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
        if ($namedArguments !== []) {
            $this->setArguments(...$namedArguments);
        }
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
            $new->runIf->push($item);
            $known->push($item->__toString());
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

    public function arguments(): array
    {
        return $this->arguments;
    }

    public function dependencies(): array
    {
        return $this->dependencies->toArray();
    }

    /**
     * @return array<ReferenceInterface|VariableInterface>
     */
    public function runIf(): array
    {
        return $this->runIf->toArray();
    }

    public function isSync(): bool
    {
        return $this->isSync;
    }

    private function setArguments(mixed ...$namedArguments): void
    {
        /** @var array<string, mixed> $namedArguments */
        $this->assertArgumentsCount($namedArguments);
        $values = [];
        $missing = [];
        $iterator = $this->parameters->getIterator();
        $iterator->rewind();
        while ($iterator->valid()) {
            $name = $iterator->key();
            $argument = $namedArguments[$name] ?? null;
            if ($argument !== null) {
                $values[$name] = $argument;
                $this->inferDependencies($argument);
            } elseif ($this->parameters->isRequired($name)) {
                $missing[] = $name;
            }
            // @infection-ignore-all
            $iterator->next();
        }
        if ($missing !== []) {
            throw new BadMethodCallException(
                message('Missing argument(s) [%arguments%]')
                    ->withCode('%arguments%', implode(', ', $missing))
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
            || $countRequired === 0
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
        if (!($argument instanceof ReferenceInterface)) {
            return;
        }
        if ($this->dependencies->contains($argument->job())) {
            return;
        }
        $this->dependencies->push($argument->job());
    }

    private function addDependencies(string ...$jobs): void
    {
        $this->assertDependencies(...$jobs);
        foreach ($jobs as $job) {
            if ($this->dependencies->contains($job)) {
                continue;
            }
            $this->dependencies->push($job);
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
