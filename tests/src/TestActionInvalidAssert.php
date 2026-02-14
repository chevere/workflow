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
use Exception;

class TestActionInvalidAssert extends Action
{
    public function __invoke(): void
    {
    }

    public static function acceptRulesStatic(): void
    {
        throw new Exception('Static rules failure for test');
    }
}
