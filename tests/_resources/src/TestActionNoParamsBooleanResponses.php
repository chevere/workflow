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
use function Chevere\Parameter\arrayp;
use function Chevere\Parameter\boolean;
use Chevere\Parameter\Interfaces\ArrayTypeParameterInterface;

final class TestActionNoParamsBooleanResponses extends Action
{
    public static function acceptResponse(): ArrayTypeParameterInterface
    {
        return arrayp(
            true: boolean(),
            false: boolean(),
        );
    }

    public function run(): array
    {
        return [
            'true' => true,
            'false' => false,
        ];
    }
}
