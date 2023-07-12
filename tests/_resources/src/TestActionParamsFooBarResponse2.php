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

namespace Chevere\Tests\_resources\src;

use Chevere\Action\Action;
use Chevere\Parameter\Interfaces\ArrayTypeParameterInterface;
use function Chevere\Parameter\arrayp;
use function Chevere\Parameter\string;

class TestActionParamsFooBarResponse2 extends Action
{
    public static function acceptResponse(): ArrayTypeParameterInterface
    {
        return arrayp(response2: string());
    }

    public function run(
        string $foo,
        string $bar
    ): array {
        return [
            'response2' => "{$foo}^{$bar}",
        ];
    }
}
