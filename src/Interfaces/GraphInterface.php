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

use Chevere\DataStructure\Interfaces\StringMappedInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;

/**
 * Describes the component in charge of defining job execution order, where each node contains async jobs.
 *
 * @extends StringMappedInterface<VectorInterface<string>>
 */
interface GraphInterface extends StringMappedInterface
{
    /**
     * Determines if the graph has the given `$job`.
     */
    public function has(string $job): bool;

    /**
     * Retrieve dependencies for the given `$job`.
     *
     * @return VectorInterface<string>
     */
    public function get(string $job): VectorInterface;

    /**
     * Determines if the given `$job` has the given `$dependencies`.
     */
    public function hasDependencies(string $job, string ...$dependencies): bool;

    /**
     * Return an instance with the specified `$name` and `$job` put.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified `$name` and `$job` put.
     */
    public function withPut(string $name, JobInterface $job): self;

    /**
     * Returns the graph as an array of arrays, where each array is a node with async jobs.
     *
     * @return array<int, array<int, string>>
     */
    public function toArray(): array;
}
