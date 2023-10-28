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
use Chevere\Parameter\Interfaces\ParameterInterface;
use function Chevere\Parameter\string;

final class TestActionIntToString extends Action
{
    public static function acceptResponse(): ParameterInterface
    {
        return string();
    }

    protected function run(int $int): string
    {
        return (string) $int;
    }
}
