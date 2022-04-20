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
use Chevere\Message\Message;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Throwable\Errors\ArgumentCountError;
use Chevere\Throwable\Exceptions\BadMethodCallException;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\UnexpectedValueException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Traits\JobDependenciesTrait;
use Ds\Vector;
use ReflectionClass;
use ReflectionException;

final class Job implements JobInterface
{
    use JobDependenciesTrait;

    /**
     * @var Array<string, mixed>
     */
    private array $arguments;

    /**
     * @var Vector<string>
     */
    private Vector $dependencies;

    private ParametersInterface $parameters;

    public function __construct(
        private string $action,
        mixed ...$namedArguments
    ) {
        try {
            // @phpstan-ignore-next-line
            $reflection = new ReflectionClass($this->action);
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException(
                (new Message("Class %action% doesn't exists"))
                    ->code('%action%', $this->action)
            );
        }
        if (!$reflection->implementsInterface(ActionInterface::class)) {
            throw new UnexpectedValueException(
                (new Message('Action %action% must implement %interface% interface'))
                    ->code('%action%', $this->action)
                    ->code('%interface%', ActionInterface::class)
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

    private function setArguments(mixed ...$namedArguments): void
    {
        /** @var array<string, mixed> $namedArguments */
        $this->assertArgumentsCount($namedArguments);
        $store = [];
        $missing = [];
        $iterator = $this->parameters->getIterator();
        $iterator->rewind();
        while ($iterator->valid()) {
            $name = $iterator->key();
            $argument = $namedArguments[$name] ?? null;
            if ($argument !== null) {
                $store[$name] = $argument;
                $this->inferDependencies($argument);
            } elseif ($this->parameters->isRequired($name)) {
                $missing[] = $name;
            }
            $iterator->next();
        }
        if ($missing !== []) {
            throw new BadMethodCallException(
                (new Message('Missing argument(s) [%arguments%]'))
                    ->code('%arguments%', implode(', ', $missing))
            );
        }
        $this->arguments = $store;
    }

    /**
     * @param mixed[] $arguments
     */
    private function assertArgumentsCount(array $arguments): void
    {
        $countPassed = count($arguments);
        $countRequired = count($this->parameters->required());
        if ($countRequired > $countPassed || $countRequired === 0 && $countPassed > 0) {
            throw new ArgumentCountError(
                (new Message('Method %action% expects values for parameters %parameters% (missing %missing%)'))
                    ->code('%action%', $this->action . '::run')
                    ->code('%missing%', implode(', ', array_diff($this->parameters->required()->toArray(), array_keys($arguments))))
                    ->code('%parameters%', implode(', ', $this->parameters->required()->toArray()))
            );
        }
    }

    private function inferDependencies(mixed $argument): void
    {
        if (!is_string($argument)) {
            return;
        }
        preg_match(self::REGEX_JOB_RESPONSE_REFERENCE, $argument, $matches);
        /** @var string[] $matches */
        if ($matches === []) {
            return;
        }
        $dependency = strval($matches[1]);
        if ($this->dependencies->contains($dependency)) {
            return;
        }
        $this->dependencies->push($dependency);
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
}
