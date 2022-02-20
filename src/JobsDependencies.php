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

use Chevere\DataStructure\Traits\MapToArrayTrait;
use Chevere\DataStructure\Traits\MapTrait;
use function Chevere\Message\message;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Workflow\Interfaces\JobsDependenciesInterface;
use Chevere\Workflow\Traits\JobDependenciesTrait;
use Ds\Vector;

final class JobsDependencies implements JobsDependenciesInterface
{
    use JobDependenciesTrait;

    use MapTrait;

    use MapToArrayTrait;

    private array $stack;

    public function withPut(string $job, string ...$dependencies): JobsDependenciesInterface
    {
        $this->assertDependencies(...$dependencies);
        $vector = new Vector($dependencies);
        $this->assertNotSelfDependency($job, $vector);
        $new = clone $this;
        foreach ($dependencies as $dependency) {
            if (!$new->has($dependency)) {
                $new->map = $new->map
                    ->withPut($dependency, new Vector());
            }
        }
        if ($new->map->has($job)) {
            $existing = $new->map->get($job);
            $vector = new Vector(
                array_unique($existing->merge($vector)->toArray())
            );
        }
        $new->handleDependencyUpdate($job, $vector);
        $new->map = $new->map->withPut($job, $vector);

        return $new;
    }

    public function has(string $job): bool
    {
        return $this->map->has($job);
    }

    public function get(string $job): Vector
    {
        return $this->map->get($job);
    }

    public function hasDependencies(string $job, string ...$dependencies): bool
    {
        $this->map->assertHas($job);
        /** @var Ds\Vector $array */
        $array = $this->map->get($job);

        return $array->contains(...$dependencies);
    }

    private function getSortAsc(): array
    {
        $array = $this->toArray();
        uasort($array, function (Vector $a, Vector $b) {
            $countA = $a->count();
            $countB = $b->count();

            return match (true) {
                $countA == $countB => 0,
                $countA < $countB => -1,
                default => 1
            };
        });

        return $array;
    }

    public function getGraph(): array
    {
        $return = [];
        $toIndex = 0;
        $previous = [];
        /** @var Vector $dependencies */
        foreach ($this->getSortAsc() as $job => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (in_array($dependency, $previous)) {
                    $toIndex++;
                    $previous = [];

                    break;
                }
            }
            
            $return[$toIndex][] = $job;
            $previous[] = $job;
        }
        
        return $return;
    }

    private function assertNotSelfDependency(string $job, Vector $vector): void
    {
        if ($vector->contains($job)) {
            throw new InvalidArgumentException(
                message('Cannot declare job %job% as dependency of itself')
                    ->code('%job%', $job)
            );
        }
    }

    private function handleDependencyUpdate(string $job, Vector $vector): void
    {
        /** @var string $dep */
        foreach ($vector as $dep) {
            if (!$this->map->has($dep)) {
                continue;
            }
            /** @var Vector $update */
            $update = $this->map->get($dep);
            unset($update[$update->find($job)]);
            $this->map = $this->map->withPut($dep, $update);
        }
    }
}
