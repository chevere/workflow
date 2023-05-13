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
use function Chevere\Parameter\integer;
use Chevere\Parameter\Interfaces\ArrayTypeParameterInterface;

/**
 * @method array run()
 *     return [
 *        'id' => (int) The id,
 *     ]
 */
final class TestActionNoParamsIntegerResponse extends Action
{
    public function getDescription(): string
    {
        return 'test';
    }

    public static function acceptResponse(): ArrayTypeParameterInterface
    {
        return arrayp(id: integer());
    }

    public function run(): array
    {
        return [
            'id' => 123,
        ];
    }
}
