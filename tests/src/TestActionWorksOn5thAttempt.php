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

final class TestActionWorksOn5thAttempt extends Action
{
    public const SUCCESS_ON_ATTEMPT = 5;

    private static int $attemptCount = 0;

    public function __invoke(): bool
    {
        ++self::$attemptCount;

        if (self::$attemptCount < self::SUCCESS_ON_ATTEMPT) {
            $attempts = self::$attemptCount;

            throw new LogicException(
                "Attempt {$attempts} failed, required attempt " . self::SUCCESS_ON_ATTEMPT
            );
        }

        return true;
    }
}
