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
use Ds\Vector;

/**
 * Describes the component in charge of defining jobs dependencies.
 */
interface JobsGraphInterface extends MappedInterface, ToArrayInterface
{
    public function has(string $job): bool;

    public function get(string $job): Vector;

    public function hasDependencies(string $job, string ...$dependencies): bool;

    public function withPut(string $job, string ...$dependencies): JobsGraphInterface;

    public function toArray(): array;
}
