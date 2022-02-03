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

use Chevere\DataStructure\Map;
use Chevere\DataStructure\Traits\MapTrait;
use Chevere\Dependent\Dependencies;
use Chevere\Dependent\Interfaces\DependenciesInterface;
use Chevere\Dependent\Interfaces\DependentInterface;
use Chevere\Message\Message;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Interfaces\StepInterface;
use Chevere\Workflow\Interfaces\StepsInterface;
use Ds\Vector;
use Iterator;

final class Steps implements StepsInterface
{
    use MapTrait;

    /**
     * @var Vector string[]
     */
    private Vector $steps;

    private DependenciesInterface $dependencies;

    public function __construct(StepInterface ...$steps)
    {
        $this->map = new Map();
        $this->steps = new Vector();
        $this->dependencies = new Dependencies();
        $this->putAdded(...$steps);
    }

    public function keys(): array
    {
        return $this->steps->toArray();
    }

    /**
     * @throws TypeError
     * @throws OutOfBoundsException
     */
    public function get(string $step): StepInterface
    {
        try {
            return $this->map->get($step);
        }
        // @codeCoverageIgnoreStart
        // @infection-ignore-all
        catch (\TypeError $e) {
            throw new TypeError(previous: $e);
        }
        // @codeCoverageIgnoreEnd
        catch (\OutOfBoundsException $e) {
            throw new OutOfBoundsException(
                (new Message('Step %name% not found'))
                    ->code('%name%', $step)
            );
        }
    }

    #[\ReturnTypeWillChange]
    public function getIterator(): Iterator
    {
        foreach ($this->steps as $step) {
            yield $step => $this->get($step);
        }
    }

    public function dependencies(): DependenciesInterface
    {
        return $this->dependencies;
    }
    
    public function has(string $step): bool
    {
        return $this->map->has($step);
    }

    public function withAdded(StepInterface ...$steps): StepsInterface
    {
        $new = clone $this;
        $new->putAdded(...$steps);

        return $new;
    }

    public function withAddedBefore(string $before, StepInterface ...$step): StepsInterface
    {
        $new = clone $this;
        $new->assertHasStepByName($before);
        foreach ($step as $name => $stepEl) {
            $new->handleStepDependencies($stepEl);
            $name = (string) $name;
            $new->addMap($name, $stepEl);
            $new->steps->insert($new->steps->find($before), $name);
        }

        return $new;
    }

    public function withAddedAfter(string $after, StepInterface ...$step): StepsInterface
    {
        $new = clone $this;
        $new->assertHasStepByName($after);
        foreach ($step as $name => $stepEl) {
            $new->handleStepDependencies($stepEl);
            $name = (string) $name;
            $new->addMap($name, $stepEl);
            $new->steps->insert($new->steps->find($after) + 1, $name);
        }

        return $new;
    }

    private function addMap(string $name, StepInterface $step): void
    {
        if ($this->map->has($name)) {
            throw new OverflowException(
                (new Message('Step name %name% has been already added.'))
                    ->code('%name%', $name)
            );
        }
        $this->map = $this->map->withPut($name, $step);
    }

    private function putAdded(StepInterface ...$steps): void
    {
        foreach ($steps as $name => $step) {
            $this->handleStepDependencies($step);
            $name = strval($name);
            $this->addMap($name, $step);
            $this->steps->push($name);
        }
    }

    private function assertHasStepByName(string $step): void
    {
        if (!$this->map->has($step)) {
            throw new OutOfBoundsException(
                (new Message("Task name %name% doesn't exists"))
                    ->code('%name%', $step)
            );
        }
    }

    private function handleStepDependencies(StepInterface $step): void
    {
        $actionName = $step->action();
        /** @var ActionInterface $action */
        $action = new $actionName();
        if ($action instanceof DependentInterface) {
            $this->dependencies = $this->dependencies
                ->withMerge($action->dependencies());
        }
    }
}
