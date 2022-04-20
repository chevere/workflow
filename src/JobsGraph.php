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

use Chevere\DataStructure\Traits\MapTrait;
use function Chevere\Message\message;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Workflow\Interfaces\JobsGraphInterface;
use Chevere\Workflow\Traits\JobDependenciesTrait;
use Ds\Vector;

final class JobsGraph implements JobsGraphInterface
{
    use JobDependenciesTrait;
    use MapTrait;

    public function withPut(string $job, string ...$dependencies): JobsGraphInterface
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
            /** @var Vector<string> $existing */
            $existing = $new->map->get($job);
            /** @var Array<string> $array */
            $array = $existing->merge($vector)->toArray();
            $vector = new Vector(array_unique($array));
        }
        $new->handleDependencyUpdate($job, $vector);
        $new->map = $new->map->withPut($job, $vector);

        return $new;
    }

    public function has(string $job): bool
    {
        return $this->map->has($job);
    }

    /**
     * @return Vector<string>
     */
    public function get(string $job): Vector
    {
        // @phpstan-ignore-next-line
        return $this->map->get($job);
    }

    public function hasDependencies(string $job, string ...$dependencies): bool
    {
        /** @var Vector<string> $array */
        $array = $this->map->get($job);

        return $array->contains(...$dependencies);
    }

    /**
     * @return Array<string, Vector<string>>
     */
    private function getSortAsc(): array
    {
        $array = iterator_to_array($this->map->getIterator(), true);
        // @phpstan-ignore-next-line
        uasort($array, function (Vector $a, Vector $b) {
            return match (true) {
                $b->contains(...$a->toArray()) => -1,
                // @infection-ignore-all
                $a->contains(...$b->toArray()) => 1,
                default => 0
            };
        });

        // @phpstan-ignore-next-line
        return $array;
    }

    public function toArray(): array
    {
        $return = [];
        $toIndex = 0;
        $previous = [];
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

    /**
     * @param Vector<string> $vector
     */
    private function assertNotSelfDependency(string $job, Vector $vector): void
    {
        if ($vector->contains($job)) {
            throw new InvalidArgumentException(
                message('Cannot declare job %job% as dependency of itself')
                    ->code('%job%', $job)
            );
        }
    }

    /**
     * @param Vector<string> $vector
     */
    private function handleDependencyUpdate(string $job, Vector $vector): void
    {
        /** @var string $dependency */
        foreach ($vector as $dependency) {
            /** @var Vector<string> $update */
            $update = $this->map->get($dependency);
            $findJob = $update->find($job);
            if ($findJob !== null) {
                // @phpstan-ignore-next-line
                unset($update[$findJob]);
            }
            $this->map = $this->map->withPut($dependency, $update);
        }
    }
}
