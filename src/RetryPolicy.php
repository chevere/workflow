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

namespace Chevere\Workflow;

use Chevere\Parameter\Attributes\_int;
use Chevere\Workflow\Interfaces\RetryPolicyInterface;
use function Chevere\Parameter\Attributes\assertArguments;

final class RetryPolicy implements RetryPolicyInterface
{
    /**
     * @param int<0, max> $timeout
     * @param int<1, max> $maxAttempts
     * @param int<0, max> $delay
     */
    public function __construct(
        #[_int(min: 0)]
        private int $timeout = 0,
        #[_int(min: 1)]
        private int $maxAttempts = 1,
        #[_int(min: 0)]
        private int $delay = 0
    ) {
        assertArguments();
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function delay(): int
    {
        return $this->delay;
    }
}
