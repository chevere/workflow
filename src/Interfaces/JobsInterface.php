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

namespace Chevere\Workflow\Interfaces;

use Chevere\DataStructure\Interfaces\MappedInterface;
use Iterator;

/**
 * Describes the component in charge of defining a collection of steps.
 */
interface JobsInterface extends MappedInterface
{
    public function __construct(JobInterface ...$jobs);

    public function has(string $name): bool;

    public function get(string $name): JobInterface;

    public function keys(): array;

    public function count(): int;

    public function jobDependencies(): JobsDependenciesInterface;

    public function withAdded(JobInterface ...$jobs): JobsInterface;

    public function withAddedBefore(string $before, JobInterface ...$job): JobsInterface;

    public function withAddedAfter(string $after, JobInterface ...$job): JobsInterface;

    /**
     * Define `...$jobs` that must be executed before job identified by `$name`.
     */
    public function withJobDependencies(string $name, string ...$jobs): JobsInterface;

    /**
     * @return Iterator<string, JobInterface>
     */
    public function getIterator(): Iterator;
}
