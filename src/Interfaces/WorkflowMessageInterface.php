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

/**
 * Describes the component in charge of defining a workflow queue.
 */
interface WorkflowMessageInterface
{
    /**
     * Return an instance with the specified `$priority`.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified `$priority`.
     */
    public function withPriority(int $priority): self;

    /**
     * Return an instance with the specified delay in `$seconds`.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified delay in `$seconds`.
     */
    public function withDelay(int $seconds): self;

    /**
     * Return an instance with the specified expiration in `$seconds`.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified expiration in `$seconds`.
     */
    public function withExpiration(int $seconds): self;

    /**
     * Provides access to the instance WorkflowRunInterface.
     */
    public function workflowRun(): WorkflowRunInterface;

    /**
     * Provides access to the token.
     */
    public function uuid(): string;

    /**
     * Provides access to the priority execution.
     */
    public function priority(): int;

    /**
     * Provides access to the delay execution.
     */
    public function delay(): int;

    /**
     * Provides access to the expiration, in milliseconds.
     */
    public function expiration(): int;
}
