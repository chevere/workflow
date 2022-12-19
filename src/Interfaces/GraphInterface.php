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

use Chevere\Common\Interfaces\ToArrayInterface;
use Chevere\DataStructure\Interfaces\MappedInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;

/**
 * Describes the component in charge of defining jobs dependencies order.
 *
 * @extends MappedInterface<VectorInterface<string>>
 */
interface GraphInterface extends MappedInterface, ToArrayInterface
{
    public function has(string $job): bool;

    /**
     * @return VectorInterface<string>
     */
    public function get(string $job): VectorInterface;

    public function hasDependencies(string $job, string ...$dependencies): bool;

    public function withPut(string $name, JobInterface $job): self;

    /**
     * @return array<int, array<int, string>>
     */
    public function toArray(): array;
}
