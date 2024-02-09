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
use Chevere\Parameter\Interfaces\ParameterInterface;
use function Chevere\Parameter\string;

class ImageResize extends Action
{
    public static function return(): ParameterInterface
    {
        return string();
    }

    protected function main(string $file, string $fit): string
    {
        $pos = strrpos($file, '.');

        return substr($file, 0, $pos)
            . ".{$fit}"
            . substr($file, $pos);
    }
}
