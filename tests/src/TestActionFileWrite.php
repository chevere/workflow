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
use Chevere\Filesystem\Interfaces\FileInterface;
use Chevere\Parameter\Interfaces\ParameterInterface;
use function Chevere\Parameter\null;

final class TestActionFileWrite extends Action
{
    public static function return(): ParameterInterface
    {
        return null();
    }

    public function main(FileInterface $file): void
    {
        $fp = fopen($file->path()->__toString(), 'a+');
        fwrite($fp, '^');
        usleep(200000);
        fwrite($fp, '$');
        fclose($fp);
    }
}
