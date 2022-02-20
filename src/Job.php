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
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Throwable\Errors\ArgumentCountError;
use Chevere\Throwable\Exceptions\BadMethodCallException;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\UnexpectedValueException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Traits\JobDependenciesTrait;
use ReflectionClass;
use ReflectionException;

final class Job implements JobInterface
{
    use JobDependenciesTrait;

    private array $arguments;

    /** @var string[] */
    private array $dependencies = [];

    private ParametersInterface $parameters;

    public function __construct(
        private string $action,
        mixed ...$namedArguments
    ) {
        try {
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
        $this->parameters = $reflection->newInstance()->getParameters();
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

    public function withDependencies(string ...$dependencies): JobInterface
    {
        $this->assertDependencies(...$dependencies);
        $new = clone $this;
        $new->dependencies = $dependencies;

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
        return $this->dependencies;
    }

    private function setArguments(mixed ...$namedArguments): void
    {
        /** @var array<string, mixed> $namedArguments */
        $this->assertArgumentsCount($namedArguments);
        $store = [];
        $missing = [];
        foreach ($this->parameters->getIterator() as $name => $parameter) {
            $argument = $namedArguments[$name] ?? null;
            if ($argument !== null) {
                $store[$name] = $argument;
            } elseif ($this->parameters->isRequired($name)) {
                $missing[] = $name;
            }
        }
        if ($missing !== []) {
            throw new BadMethodCallException(
                (new Message('Missing argument(s) %arguments%'))
                    ->code('%arguments%', implode(', ', $missing))
            );
        }
        $this->arguments = $store;
    }

    private function assertArgumentsCount(array $arguments): void
    {
        $countPassed = count($arguments);
        $countRequired = count($this->parameters->required());
        if ($countRequired > $countPassed || $countRequired === 0 && $countPassed > 0) {
            throw new ArgumentCountError(
                (new Message('Method %action% expects %interface% providing %parametersCount% arguments, %given% given'))
                    ->code('%action%', $this->action . '::run')
                    ->code('%interface%', ArgumentsInterface::class)
                    ->code('%parametersCount%', (string) $countRequired)
                    ->code('%given%', (string) $countPassed)
            );
        }
    }
}
