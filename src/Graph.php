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
        $jobLevels = [];
        $sync = [];
        foreach ($this->getSortAsc() as $job => $dependencies) {
            $maxDependencyLevel = -1;
            foreach ($dependencies as $dependency) {
                if (isset($jobLevels[$dependency])) {
                    $maxDependencyLevel = max($maxDependencyLevel, $jobLevels[$dependency]);
                }
            }
            $jobLevel = $maxDependencyLevel + 1;
            $sort[$jobLevel][] = $job;
            $jobLevels[$job] = $jobLevel;
            if ($this->jobs->find($job) !== null) {
                $sync[$job] = $jobLevel;
            }
        }

        return $this->getSortJobs($sort, $sync);
    }

    /**
     * @return array<string, VectorInterface<string>>
     * @infection-ignore-all
     */
    private function getSortAsc(): array
    {
        $array = $this->map->toArray();
        uksort($array, function (int|string $jobA, int|string $jobB) use ($array): int {
            /** @var VectorInterface<string> $depsA */
            $depsA = $array[$jobA];
            /** @var VectorInterface<string> $depsB */
            $depsB = $array[$jobB];

            return match (true) {
                $depsB->contains($jobA) => -1,
                $depsA->contains($jobB) => 1,
                default => $depsA->count() <=> $depsB->count()
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
        if (empty($sync)) {
            return array_values($sort);
        }
        $result = [];
        $resultIndex = 0;
        foreach ($sort as $jobs) {
            $syncJobs = [];
            $asyncJobs = [];
            foreach ($jobs as $job) {
                if (isset($sync[$job])) {
                    $syncJobs[] = $job;
                } else {
                    $asyncJobs[] = $job;
                }
            }
            foreach ($syncJobs as $syncJob) {
                $result[$resultIndex++] = [$syncJob];
            }
            if (! empty($asyncJobs)) {
                $result[$resultIndex++] = $asyncJobs;
            }
        }

        return array_values($result);
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
