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

use Chevere\Parameter\Attributes\_int;
use Chevere\Parameter\Attributes\_return;

class TestInvocableClass
{
    #[_return(new _int(min: 1))]
    public function __invoke(
        #[_int(min: 2)]
        int $id
    ): int {
        return $id;
    }
}
