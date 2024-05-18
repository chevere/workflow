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
use Chevere\Parameter\Interfaces\NullParameterInterface;
use RuntimeException;
use function Chevere\Parameter\null;

class StoreFile extends Action
{
    public static function return(): NullParameterInterface
    {
        return null();
    }

    protected function main(string $file, string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (! rename($file, $dir . basename($file))) {
            throw new RuntimeException('Unable to store file');
        }
    }
}
