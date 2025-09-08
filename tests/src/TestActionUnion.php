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

class TestActionUnion extends Action
{
    public function __invoke(
        int|float $foo,
    ): array {
        return [
            'foo' => $foo,
        ];
    }
}
