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
use Chevere\Dependent\Interfaces\DependenciesInterface;
use Iterator;

/**
 * Describes the component in charge of defining a collection of steps.
 */
interface StepsInterface extends MappedInterface
{
    public function __construct(StepInterface ...$steps);

    public function has(string $name): bool;

    public function get(string $name): StepInterface;

    public function dependencies(): DependenciesInterface;

    public function keys(): array;

    public function count(): int;

    public function withAdded(StepInterface ...$steps): StepsInterface;

    public function withAddedBefore(string $before, StepInterface ...$step): StepsInterface;

    public function withAddedAfter(string $after, StepInterface ...$step): StepsInterface;

    /**
     * @return Iterator<string, StepInterface>
     */
    public function getIterator(): Iterator;
}
