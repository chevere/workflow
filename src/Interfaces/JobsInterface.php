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

use Chevere\DataStructure\Interfaces\MapInterface;
use Chevere\DataStructure\Interfaces\StringMappedInterface;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Iterator;

/**
 * Describes the component in charge of defining a collection of Jobs.
 *
 * @extends StringMappedInterface<JobInterface>
 */
interface JobsInterface extends StringMappedInterface
{
    public function has(string $job): bool;

    public function get(string $job): JobInterface;

    /**
     * @return MapInterface<ParameterInterface>
     */
    public function variables(): MapInterface;

    /**
     * @return MapInterface<ParameterInterface>
     */
    public function references(): MapInterface;

    /**
     * @return string[]
     */
    public function keys(): array;

    public function graph(): GraphInterface;

    public function withAdded(JobInterface ...$jobs): self;

    /**
     * @return Iterator<string, JobInterface>
     */
    public function getIterator(): Iterator;
}
