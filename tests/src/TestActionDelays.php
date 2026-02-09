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
use function Amp\delay;

/**
 * A test action that delays execution for testing timeout functionality.
 */
final class TestActionDelays extends Action
{
    public function __invoke(float $seconds): bool
    {
        delay($seconds);

        return true;
    }
}
