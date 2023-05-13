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
use Chevere\Attribute\StringAttribute;
use function Chevere\Parameter\arrayp;
use Chevere\Parameter\Interfaces\ArrayTypeParameterInterface;
use function Chevere\Parameter\string;

class TestActionParamFooResponseBar extends Action
{
    public static function acceptResponse(): ArrayTypeParameterInterface
    {
        return arrayp(
            bar: string('/^bar$/')
        );
    }

    public function run(
        #[StringAttribute('/^bar$/')]
        string $foo
    ): array {
        return [
            'bar' => 'bar',
        ];
    }
}
