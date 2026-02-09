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
 * Defines retry policy configuration for job execution.
 */
interface RetryPolicyInterface
{
    /**
     * Returns the maximum execution time in seconds.
     *
     * The job fails when this timeout is exceeded, regardless of remaining attempts.
     * A value of 0 indicates no timeout limit.
     *
     * @return int<0, max> Timeout in seconds (0 = unlimited)
     */
    public function timeout(): int;

    /**
     * Returns the maximum number of execution attempts.
     *
     * This includes the initial attempt. For example, a value of 3 means
     * one initial attempt plus two retries.
     *
     * @return positive-int Number of attempts (minimum 1)
     */
    public function maxAttempts(): int;

    /**
     * Returns the delay between retry attempts in seconds.
     *
     * Applied before each subsequent attempt (not before the initial attempt).
     * A value of 0 means immediate retry.
     *
     * @return int<0, max> Delay in seconds (0 = no delay)
     */
    public function delay(): int;
}
