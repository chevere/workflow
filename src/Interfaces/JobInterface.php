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
use Chevere\Caller\Interfaces\CallerInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Closure;

/**
 * Describes the component in charge of defining a job.
 */
interface JobInterface
{
    /**
     * Provides access to the invocable ActionInterface|Closure parameters for this job.
     */
    public function parameters(): ParametersInterface;

    /**
     * Provides access to the return type parameter for this job.
     */
    public function return(): ParameterInterface;

    /**
     * @return ActionInterface|class-string<ActionInterface>|Closure
     */
    public function action(): ActionInterface|string|Closure;

    /**
     * @return array<string, mixed>
     */
    public function arguments(): array;

    /**
     * @return VectorInterface<string>
     */
    public function dependencies(): VectorInterface;

    /**
     * @return bool True if the job is synchronous (blocking)
     */
    public function isSync(): bool;

    /**
     * @return VectorInterface<ResponseReferenceInterface|VariableInterface|callable|bool>
     */
    public function runIf(): VectorInterface;

    /**
     * @return VectorInterface<ResponseReferenceInterface|VariableInterface|callable|bool>
     */
    public function runIfNot(): VectorInterface;

    /**
     * Provides access to the caller who created this job.
     */
    public function caller(): CallerInterface;

    /**
     * Provides access to the job retry policy.
     */
    public function retryPolicy(): RetryPolicyInterface;

    /**
     * Return an instance with the specified arguments.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified arguments.
     */
    public function withArguments(mixed ...$argument): self;

    /**
     * Return an instance with the specified run-if condition.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified run-if condition.
     */
    public function withRunIf(ResponseReferenceInterface|VariableInterface|callable|bool|int ...$context): self;

    /**
     * Return an instance with the specified run-if-not condition.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified run-if-not condition.
     */
    public function withRunIfNot(ResponseReferenceInterface|VariableInterface|callable|bool|int ...$context): self;

    /**
     * Return an instance with the specified sync flag.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified sync flag.
     *
     * @param bool $flag True for sync, false for async.
     */
    public function withIsSync(bool $flag = true): self;

    /**
     * Return an instance with the specified job dependencies.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified job dependencies.
     */
    public function withDepends(string ...$jobs): self;

    /**
     * @param int<0, max> $timeout Timeout in seconds across all attempts (0 = unlimited)
     * @param int<1, max> $maxAttempts Number of attempts (minimum 1)
     * @param int<0, max> $delay Retry delay in seconds (0 = no delay)
     */
    public function withRetry(
        int $timeout = 0,
        int $maxAttempts = 1,
        int $delay = 0
    ): self;
}
