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
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsGraphInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Ds\Vector;
use Iterator;

final class Jobs implements JobsInterface
{
    use MapTrait;

    private Vector $jobs;

    private JobsGraphInterface $graph;

    public function __construct(JobInterface ...$jobs)
    {
        $this->map = new Map();
        $this->jobs = new Vector();
        $this->graph = new JobsGraph();
        $this->putAdded(...$jobs);
    }

    public function keys(): array
    {
        return $this->jobs->toArray();
    }

    public function getGraph(): array
    {
        return $this->graph->getGraph();
    }

    /**
     * @throws TypeError
     * @throws OutOfBoundsException
     */
    public function get(string $job): JobInterface
    {
        try {
            return $this->map->get($job);
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
                    ->code('%name%', $job)
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
            $this->assertJobContainsDependencies($name, $job);
            $this->graph = $this->graph
                ->withPut($name, ...$job->dependencies());
        }
    }

    private function assertJobContainsDependencies(string $name, JobInterface $job): void
    {
        if (!$this->jobs->contains(...$job->dependencies())) {
            $missing = array_diff_assoc($job->dependencies(), $this->jobs->toArray());

            throw new InvalidArgumentException(
                message('Job %job% has undeclared job dependencies: %dependencies%')
                    ->code('%job%', $name)
                    ->code(
                        '%dependencies%',
                        implode(', ', $missing)
                    )
            );
        }
    }

    private function assertHasJobByName(string $job): void
    {
        if (!$this->map->has($job)) {
            throw new OutOfBoundsException(
                (new Message("Job %name% doesn't exists"))
                    ->code('%name%', $job)
            );
        }
    }
}
