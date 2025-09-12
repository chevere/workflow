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

namespace Chevere\Demo\Actions;

use Chevere\Action\Action;
use Chevere\Parameter\Interfaces\StringParameterInterface;
use function Chevere\Parameter\string;

class Greet extends Action
{
    public function __invoke(string $username): string
    {
        return "Hello, {$username}!";
    }

    public static function acceptReturn(): StringParameterInterface
    {
        return string('/^Hello, /');
    }
}
