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
    private VectorInterface $jobs;

    public function __construct()
    {
        $this->map = new Map();
        $this->jobs = new Vector();
    }

    public function withPut(
        string $name,
        JobInterface $job,
    ): GraphInterface {
        $directDeps = $job->dependencies();
        $transitive = $this->computeTransitiveClosure($directDeps);
        $this->assertNotSelfDependency($name, $transitive);
        $new = clone $this;
        /** @var string $dep */
        foreach ($directDeps as $dep) {
            if (! $new->map->has($dep)) {
                $new->map = $new->map
                    ->withPut($dep, new Vector());
            }
        }
        if ($new->map->has($name)) {
            /** @var VectorInterface<string> $existing */
            $existing = $new->map->get($name);
            $merged = array_unique(
                array_merge($existing->toArray(), $directDeps->toArray())
            );
            $new->map = $new->map->withPut($name, new Vector(...$merged));
        } else {
            $new->map = $new->map->withPut($name, $directDeps);
        }
        $found = $new->jobs->find($name);
        if ($job->isSync()) {
            if ($found === null) {
                $new->jobs = $new->jobs->withPush($name);
            }
        } elseif ($found !== null) {
            $new->jobs = $new->jobs->withRemove($found);
        }

        return $new;
    }

    public function has(string $job): bool
    {
        return $this->map->has($job);
    }

    public function get(string $job): VectorInterface
    {
        /** @var VectorInterface<string> $directDeps */
        $directDeps = $this->map->get($job);

        return $this->computeTransitiveClosure($directDeps);
    }

    public function hasDependencies(string $job, string ...$dependencies): bool
    {
        return $this->get($job)->contains(...$dependencies);
    }

    public function toArray(): array
    {
        /** @var array<string, VectorInterface<string>> $map */
        $map = $this->map->toArray();
        /** @var array<string, list<string>> $successors */
        $successors = [];
        /** @var array<string, int> $inDegree */
        $inDegree = [];
        foreach ($map as $job => $deps) {
            $job = (string) $job;
            if (! isset($successors[$job])) {
                $successors[$job] = [];
            }
            $inDegree[$job] = $deps->count();
            /** @var string $dep */
            foreach ($deps as $dep) {
                $successors[$dep][] = $job;
            }
        }
        $queue = [];
        foreach ($inDegree as $job => $degree) {
            if ($degree === 0) {
                $queue[] = (string) $job;
            }
        }
        $levels = [];
        while (! empty($queue)) {
            $levels[] = $queue;
            $nextQueue = [];
            foreach ($queue as $job) {
                foreach ($successors[$job] as $successor) {
                    $inDegree[$successor]--;
                    if ($inDegree[$successor] === 0) {
                        $nextQueue[] = $successor;
                    }
                }
            }
            $queue = $nextQueue;
        }

        return $this->splitSyncBatches($levels);
    }

    /**
     * @param array<int, array<int, string>> $levels
     * @return array<int, array<int, string>>
     */
    private function splitSyncBatches(array $levels): array
    {
        $syncSet = [];
        /** @var string $job */
        foreach ($this->jobs as $job) {
            $syncSet[$job] = true;
        }
        if (empty($syncSet)) {
            return $levels;
        }
        $result = [];
        foreach ($levels as $jobs) {
            $syncJobs = [];
            $asyncJobs = [];
            foreach ($jobs as $job) {
                if (isset($syncSet[$job])) {
                    $syncJobs[] = $job;
                } else {
                    $asyncJobs[] = $job;
                }
            }
            foreach ($syncJobs as $syncJob) {
                $result[] = [$syncJob];
            }
            if (! empty($asyncJobs)) {
                $result[] = $asyncJobs;
            }
        }

        return $result;
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
     * Example:
     *   A depends on [B]
     *   B depends on [C]
     *   C depends on [D]
     *
     * computeTransitiveClosure([B]) returns [B, C, D]
     *
     * @param VectorInterface<string> $directDeps Direct dependencies
     * @return VectorInterface<string> All dependencies (direct + transitive)
     */
    private function computeTransitiveClosure(VectorInterface $directDeps): VectorInterface
    {
        $closed = new Vector();
        $queue = $directDeps->toArray();
        $visited = [];
        while (! empty($queue)) {
            $dep = array_shift($queue);
            if (isset($visited[$dep])) {
                continue;
            }
            $visited[$dep] = true;
            $closed = $closed->withPush($dep);
            if ($this->map->has($dep)) {
                /** @var VectorInterface<string> $subDeps */
                $subDeps = $this->map->get($dep);
                foreach ($subDeps->toArray() as $subDep) {
                    if (! isset($visited[$subDep])) {
                        $queue[] = $subDep;
                    }
                }
            }
        }

        return $closed;
    }
}
