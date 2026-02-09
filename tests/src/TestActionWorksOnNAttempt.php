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

namespace Chevere\Tests\src;

use Chevere\Action\Action;
use LogicException;

final class TestActionWorksOnNAttempt extends Action
{
    private static int $attemptCount = 0;

    public function __construct(
        private int $successOnAttempt
    ) {
        self::$attemptCount = 0;
    }

    public function __invoke(): bool
    {
        ++self::$attemptCount;

        if (self::$attemptCount < $this->successOnAttempt) {
            $attempts = self::$attemptCount;

            throw new LogicException(
                "Attempt {$attempts} failed, required attempt {$this->successOnAttempt}"
            );
        }

        return true;
    }
}
