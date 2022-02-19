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
use Chevere\Filesystem\Interfaces\PathInterface;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\ObjectParameter;
use Chevere\Parameter\Parameters;
use Chevere\Parameter\StringParameter;
use Chevere\Response\Interfaces\ResponseInterface;

class WorkflowTestStep2Conflict extends Action
{
    public function getParameters(): ParametersInterface
    {
        return new Parameters(
            baz: new ObjectParameter(PathInterface::class),
            bar: new StringParameter()
        );
    }

    public function run(ArgumentsInterface $arguments): ResponseInterface
    {
        return $this->getResponse();
    }
}
