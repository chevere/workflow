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
use function Chevere\Message\message;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Workflow\Interfaces\GraphInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Ds\Vector;

final class Graph implements GraphInterface
{
    use MapTrait;

    /**
     * @var Vector<string>
     */
    private Vector $syncJobs;

    public function __construct()
    {
        $this->map = new Map();
        $this->syncJobs = new Vector();
    }

    public function withPut(
        string $name,
        JobInterface $job,
    ): GraphInterface {
        $vector = new Vector($job->dependencies());
        $this->assertNotSelfDependency($name, $vector);
        $new = clone $this;
        foreach ($job->dependencies() as $dependency) {
            if (!$new->has($dependency)) {
                $new->map = $new->map
                    ->withPut($dependency, new Vector());
            }
        }
        if ($new->map->has($name)) {
            /** @var Vector<string> $existing */
            $existing = $new->map->get($name);
            /** @var Array<string> $array */
            $array = $existing->merge($vector)->toArray();
            $vector = new Vector($array);
        }
        $new->handleDependencyUpdate($name, $vector);
        $new->map = $new->map->withPut($name, $vector);
        if ($job->isSync()) {
            $new->syncJobs->push($name);
        }

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

    /**
     * @return array<int, array<int, string>>
     */
    public function toArray(): array
    {
        $sort = [];
        $previous = [];
        $sync = [];
        $toIndex = 0;
        foreach ($this->getSortAsc() as $job => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (in_array($dependency, $previous)) {
                    $toIndex++;
                    $previous = [];

                    break;
                }
            }
            $sort[$toIndex][] = $job;
            $previous[] = $job;
            if ($this->syncJobs->contains($job)) {
                $sync[$job] = $toIndex;
            }
        }

        return $this->getSortJobs($sort, $sync);
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
        $vector = new Vector($sort);
        foreach ($sync as $syncJob => $indexKey) {
            $auxIndexKey = $indexKey + $aux;
            $array = $vector->get($auxIndexKey);
            $key = array_search($syncJob, $array);
            unset($array[$key]);
            $array = array_values($array);
            $vector->offsetSet($auxIndexKey, $array);
            $vector->insert($auxIndexKey, [$syncJob]);
            $aux++;
        }

        return array_values(array_filter($vector->toArray()));
    }

    /**
     * @param Vector<string> $vector
     */
    private function assertNotSelfDependency(string $job, Vector $vector): void
    {
        if ($vector->contains($job)) {
            throw new InvalidArgumentException(
                message('Cannot declare job %job% as a self-dependency')
                    ->withCode('%job%', $job)
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
                unset($update[$findJob]);
            }
            $this->map = $this->map->withPut($dependency, $update);
        }
    }
}
