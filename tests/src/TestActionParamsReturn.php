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
use Chevere\Parameter\Attributes\_arrayp;
use Chevere\Parameter\Attributes\_return;
use Chevere\Parameter\Attributes\_string;

class TestActionParamsReturn extends Action
{
    #[_return(
        new _arrayp(
            foo: new _string(),
            bar: new _string()
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
