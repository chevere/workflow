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

use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\DataStructure\Map;
use Chevere\DataStructure\Traits\MapTrait;
use Chevere\DataStructure\Vector;
use Chevere\Workflow\Interfaces\GraphInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use InvalidArgumentException;
use function Chevere\Message\message;

final class Graph implements GraphInterface
{
    /**
     * @template-use MapTrait<VectorInterface<string>>
     */
    use MapTrait;

    /**
     * @var VectorInterface<string>
     */
    private VectorInterface $syncJobs;

    public function __construct()
    {
        $this->map = new Map();
        $this->syncJobs = new Vector();
    }

    public function withPut(
        string $name,
        JobInterface $job,
    ): GraphInterface {
        $vector = $job->dependencies();
        $this->assertNotSelfDependency($name, $vector);
        $new = clone $this;
        foreach ($vector as $dependency) {
            if (! $new->has($dependency)) {
                $new->map = $new->map
                    ->withPut($dependency, new Vector());
            }
        }
        if ($new->map->has($name)) {
            /** @var VectorInterface<string> $existing */
            $existing = $new->map->get($name);
            $merge = array_merge($existing->toArray(), $vector->toArray());
            $vector = new Vector(...$merge);
        }
        $new->handleDependencyUpdate($name, $vector);
        $new->map = $new->map->withPut($name, $vector);
        if ($job->isSync()) {
            $new->syncJobs = $new->syncJobs->withPush($name);
        }

        return $new;
    }

    public function has(string $job): bool
    {
        return $this->map->has($job);
    }

    public function get(string $job): VectorInterface
    {
        /** @var VectorInterface<string> */
        return $this->map->get($job);
    }

    public function hasDependencies(string $job, string ...$dependencies): bool
    {
        /** @var VectorInterface<string> $array */
        $array = $this->map->get($job);

        return $array->contains(...$dependencies);
    }

    public function toArray(): array
    {
        $sort = [];
        $previous = [];
        $sync = [];
        $toIndex = 0;
        foreach ($this->getSortAsc() as $job => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (in_array($dependency, $previous, true)) {
                    $toIndex++;
                    $previous = [];

                    break;
                }
            }
            $sort[$toIndex][] = $job;
            $previous[] = $job;
            if ($this->syncJobs->find($job) !== null) {
                $sync[$job] = $toIndex;
            }
        }

        return $this->getSortJobs($sort, $sync);
    }

    /**
     * @return array<string, VectorInterface<string>>
     */
    private function getSortAsc(): array
    {
        $array = $this->map->toArray();
        uasort($array, function (VectorInterface $a, VectorInterface $b) {
            return match (true) {
                $b->contains(...$a->toArray()) => -1,
                // @infection-ignore-all
                $a->contains(...$b->toArray()) => 1,
                default => 0
            };
        });

        /* @phpstan-ignore-next-line */
        return $array;
    }

    /**
     * @param array<int, array<int, string>> $sort
     * @param array<string, int> $sync
     * @return array<int, array<int, string>>
     */
    private function getSortJobs(array $sort, array $sync): array
    {
        if (count($this->syncJobs) === 0) {
            return $sort;
        }
        $aux = 0;
        $vector = new Vector(...$sort);
        foreach ($sync as $job => $index) {
            $auxIndex = $index + $aux;
            /** @var array<int, string> $array */
            $array = $vector->get($auxIndex);
            $key = array_search($job, $array, true);
            unset($array[$key]);
            $array = array_values($array);
            $vector = $vector
                ->withSet($auxIndex, $array)
                ->withInsert($auxIndex, [$job]);
            $aux++;
        }
        /** @var array<int, array<int, string>> */
        $array = $vector->toArray();

        return array_values(
            array_filter($array)
        );
    }

    /**
     * @param VectorInterface<string> $vector
     */
    private function assertNotSelfDependency(string $job, VectorInterface $vector): void
    {
        if (! $vector->contains($job)) {
            return;
        }

        throw new InvalidArgumentException(
            (string) message(
                'Cannot declare job **%job%** as a self-dependency',
                job: $job
            )
        );
    }

    /**
     * @param VectorInterface<string> $vector
     */
    private function handleDependencyUpdate(string $job, VectorInterface $vector): void
    {
        /** @var string $dependency */
        foreach ($vector as $dependency) {
            /** @var VectorInterface<string> $update */
            $update = $this->map->get($dependency);
            $findJob = $update->find($job);
            if ($findJob !== null) {
                $update = $update->withRemove($findJob);
            }
            $this->map = $this->map->withPut($dependency, $update);
        }
    }
}
