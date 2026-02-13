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
use Chevere\Parameter\Attributes\_int;
use Chevere\Parameter\Attributes\_return;
use Chevere\Parameter\Interfaces\IntParameterInterface;
use function Chevere\Parameter\int;

/**
 * Method `acceptReturn()` has higher priority than `_return`, which is ignored.
 */
final class TestActionIntParam_return extends Action
{
    #[_return(
        new _int(min: 0)
    )]
    public function __invoke(
        #[_int(min: 1)]
        int $number
    ): int {
        return 2 * $number;
    }

    public static function acceptReturn(): IntParameterInterface
    {
        return int(min: 8);
    }
}
