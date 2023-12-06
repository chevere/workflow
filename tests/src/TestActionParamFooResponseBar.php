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
use Chevere\Parameter\Attributes\StringAttr;
use Chevere\Parameter\Interfaces\ParameterInterface;
use function Chevere\Parameter\arrayp;
use function Chevere\Parameter\float;
use function Chevere\Parameter\string;

class TestActionParamFooResponseBar extends Action
{
    public static function acceptResponse(): ParameterInterface
    {
        return arrayp(
            bar: string('/^bar$/'),
            baz: float(),
        );
    }

    public function run(
        #[StringAttr('/^bar$/')]
        string $foo
    ): array {
        return [
            'bar' => 'bar',
        ];
    }
}
