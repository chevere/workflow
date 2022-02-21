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
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use function Chevere\Parameter\objectParameter;
use Chevere\Parameter\Parameters;
use Chevere\Response\Interfaces\ResponseInterface;

final class TestActionWrite extends Action
{
    public function getDescription(): string
    {
        return 'test';
    }

    public function getParameters(): ParametersInterface
    {
        return new Parameters(
            file: objectParameter(FileInterface::class),
        );
    }

    public function run(ArgumentsInterface $arguments): ResponseInterface
    {
        /** @var FileInterface $file */
        $file = $arguments->get('file');
        $fp = fopen($file->path()->__toString(), 'a+');
        fwrite($fp, $this->flagStart());
        usleep(10000);
        fwrite($fp, $this->flagFinish());
        fclose($fp);

        return $this->getResponse();
    }

    public function flagStart(): string
    {
        return '^';
    }

    public function flagFinish(): string
    {
        return '$';
    }
}
