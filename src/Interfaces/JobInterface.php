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

use Chevere\Action\Interfaces\ActionInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;

/**
 * Describes the component in charge of defining a job.
 */
interface JobInterface
{
    public function withArguments(mixed ...$argument): self;

    public function withRunIf(ReferenceInterface|VariableInterface ...$context): self;

    /**
     * Return an instance with the specified sync flag.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified sync flag.
     *
     * @param bool $flag True for sync, false for async.
     */
    public function withIsSync(bool $flag = true): self;

    public function withDepends(string ...$jobs): self;

    public function action(): ActionInterface;

    /**
     * @return array<string, mixed>
     */
    public function arguments(): array;

    /**
     * @return VectorInterface<string>
     */
    public function dependencies(): VectorInterface;

    public function isSync(): bool;

    /**
     * @return VectorInterface<ReferenceInterface|VariableInterface>
     */
    public function runIf(): VectorInterface;
}
