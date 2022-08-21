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
use Chevere\DataStructure\Map;
use Chevere\Type\Interfaces\TypeInterface;
use Iterator;

/**
 * Describes the component in charge of defining a collection of steps.
 */
interface JobsInterface extends MappedInterface
{
    public function __construct(JobInterface ...$jobs);

    public function has(string $job): bool;

    public function get(string $job): JobInterface;

    /** @return Map<string, TypeInterface> */
    public function variables(): Map;

    /** @return Map<string, TypeInterface> */
    public function references(): Map;

    /**
     * @return string[]
     */
    public function keys(): array;

    public function count(): int;

    /** @return Array<int, string[]> */
    public function graph(): array;

    public function withAdded(JobInterface ...$jobs): JobsInterface;

    /**
     * @return Iterator<string, JobInterface>
     */
    public function getIterator(): Iterator;
}
