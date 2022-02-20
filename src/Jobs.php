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
use Chevere\Message\Message;
use function Chevere\Message\message;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\LogicException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsDependenciesInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Ds\Vector;
use Iterator;

final class Jobs implements JobsInterface
{
    use MapTrait;

    private Vector $jobs;

    private JobsDependenciesInterface $jobDependencies;

    public function __construct(JobInterface ...$jobs)
    {
        $this->map = new Map();
        $this->jobs = new Vector();
        $this->jobDependencies = new JobsDependencies();
        $this->putAdded(...$jobs);
    }

    public function keys(): array
    {
        return $this->jobs->toArray();
    }

    public function jobDependencies(): JobsDependenciesInterface
    {
        return $this->jobDependencies;
    }

    /**
     * @throws TypeError
     * @throws OutOfBoundsException
     */
    public function get(string $jobs): JobInterface
    {
        try {
            return $this->map->get($jobs);
        }
        // @codeCoverageIgnoreStart
        // @infection-ignore-all
        catch (\TypeError $e) {
            throw new TypeError(previous: $e);
        }
        // @codeCoverageIgnoreEnd
        catch (\OutOfBoundsException $e) {
            throw new OutOfBoundsException(
                (new Message('Job %name% not found'))
                    ->code('%name%', $jobs)
            );
        }
    }

    #[\ReturnTypeWillChange]
    public function getIterator(): Iterator
    {
        foreach ($this->jobs as $job) {
            yield $job => $this->get($job);
        }
    }

    public function has(string $job): bool
    {
        return $this->map->has($job);
    }

    public function withAdded(JobInterface ...$jobs): JobsInterface
    {
        $new = clone $this;
        $new->putAdded(...$jobs);

        return $new;
    }

    public function withAddedBefore(string $before, JobInterface ...$job): JobsInterface
    {
        $new = clone $this;
        $new->assertHasJobByName($before);
        foreach ($job as $name => $jobEl) {
            $name = (string) $name;
            $new->addMap($name, $jobEl);
            $new->jobs->insert($new->jobs->find($before), $name);
        }

        return $new;
    }

    public function withAddedAfter(string $after, JobInterface ...$job): JobsInterface
    {
        $new = clone $this;
        $new->assertHasJobByName($after);
        foreach ($job as $name => $jobEl) {
            $name = (string) $name;
            $new->addMap($name, $jobEl);
            $new->jobs->insert($new->jobs->find($after) + 1, $name);
        }

        return $new;
    }

    public function withJobDependencies(string $name, string ...$jobs): JobsInterface
    {
        $new = clone $this;
        $new->assertHasJobByName($name);
        foreach ($jobs as $job) {
            $new->assertHasJobByName($job);
            if ($new->jobDependencies->has($job)
                && $new->jobDependencies->hasDependencies($job, $name)) {
                throw new LogicException(
                    message("Job %name% can't depend on %job% (%job% depends on %name%).")
                            ->code('%job%', $job)
                            ->code('%name%', $name)
                );
            }
        }
        $new->jobDependencies = $new->jobDependencies
            ->withPut($name, ...$jobs);

        return $new;
    }

    private function addMap(string $name, JobInterface $job): void
    {
        if ($this->map->has($name)) {
            throw new OverflowException(
                (new Message('Job name %name% has been already added.'))
                    ->code('%name%', $name)
            );
        }
        $this->map = $this->map->withPut($name, $job);
    }

    private function putAdded(JobInterface ...$jobs): void
    {
        foreach ($jobs as $name => $job) {
            $name = strval($name);
            $this->addMap($name, $job);
            $this->jobs->push($name);
            $this->jobDependencies = $this->jobDependencies
                ->withPut($name, ...$job->dependencies());
        }
    }

    private function assertHasJobByName(string $jobs): void
    {
        if (!$this->map->has($jobs)) {
            throw new OutOfBoundsException(
                (new Message("Job %name% doesn't exists"))
                    ->code('%name%', $jobs)
            );
        }
    }
}
