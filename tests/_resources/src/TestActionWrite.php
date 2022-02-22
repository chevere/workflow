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
use Chevere\Filesystem\Interfaces\FileInterface;
use Chevere\Response\Interfaces\ResponseInterface;

final class TestActionWrite extends Action
{
    public function getDescription(): string
    {
        return 'test';
    }

    public function run(FileInterface $file): ResponseInterface
    {
        $fp = fopen($file->path()->__toString(), 'a+');
        fwrite($fp, '^');
        usleep(10000);
        fwrite($fp, '$');
        fclose($fp);

        return $this->getResponse();
    }
}
