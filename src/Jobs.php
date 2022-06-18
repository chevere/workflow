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
use Chevere\DataStructure\Map;
use Chevere\DataStructure\Traits\MapTrait;
use function Chevere\Message\message;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Interfaces\GraphInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\JobsInterface;
use Chevere\Workflow\Interfaces\ReferenceInterface;
use Ds\Vector;
use Iterator;
use Throwable;

final class Jobs implements JobsInterface
{
    use MapTrait;

    /**
     * @var Vector<string>
     */
    private Vector $jobs;

    private GraphInterface $graph;

    public function __construct(JobInterface ...$jobs)
    {
        $this->map = new Map();
        $this->jobs = new Vector();
        $this->graph = new Graph();
        $this->putAdded(...$jobs);
    }

    public function keys(): array
    {
        return $this->jobs->toArray();
    }

    public function graph(): array
    {
        return $this->graph->toArray();
    }

    public function get(string $job): JobInterface
    {
        try {
            // @phpstan-ignore-next-line
            return $this->map->get($job);
        }
        // @codeCoverageIgnoreStart
        // @infection-ignore-all
        // @phpstan-ignore-next-line
        catch (\TypeError $e) {
            throw new TypeError(previous: $e);
        }
        // @codeCoverageIgnoreEnd
        catch (\OutOfBoundsException $e) {
            throw new OutOfBoundsException(
                message('Job %name% not found')
                    ->withCode('%name%', $job)
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
                message('Job name %name% has been already added.')
                    ->withCode('%name%', $name)
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
            $this->graph = $this->graph->withPut($name, $job);
        }
    }

    private function assertJobContainsDependencies(
        string $name,
        JobInterface $job
    ): void {
        $dependencies = $job->dependencies();
        foreach ($job->runIf() as $runIf) {
            if ($runIf instanceof ReferenceInterface) {
                $dependencies[] = $runIf->job();
                // /** @var JobInterface $runIfJob */
                // $runIfJob = $this->map->get($runIf->job());
                // /** @var ActionInterface $action */
                // $action = new ($runIfJob->action());

                // try {
                //     $parameter = $action->getResponseParameters()
                //         ->get($runIf->key());
                //     vdd($parameter);
                // } catch (Throwable $e) {
                //     throw new OutOfBoundsException(
                //         message('')
                //     );
                // }
            }
        }
        $dependencies = array_filter($dependencies);
        if (!$this->jobs->contains(...$dependencies)) {
            $missing = array_diff(
                $dependencies,
                $this->jobs->toArray()
            );

            throw new OutOfBoundsException(
                message('Job %job% has undeclared dependencies: %dependencies%')
                    ->withCode('%job%', $name)
                    ->withCode(
                        '%dependencies%',
                        implode(', ', $missing)
                    )
            );
        }
    }
}
