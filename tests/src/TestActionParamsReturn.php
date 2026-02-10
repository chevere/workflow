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
use Chevere\Parameter\Attributes\ArrayAttr;
use Chevere\Parameter\Attributes\ReturnAttr;
use Chevere\Parameter\Attributes\StringAttr;

class TestActionParamsReturn extends Action
{
    #[ReturnAttr(
        new ArrayAttr(
            foo: new StringAttr(),
            bar: new StringAttr()
        )
    )]
    public function __invoke(string $foo, string $bar): array
    {
        return [
            'foo' => $foo,
            'bar' => $bar,
        ];
    }
}
