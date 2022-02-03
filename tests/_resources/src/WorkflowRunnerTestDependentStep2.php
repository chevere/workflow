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

namespace Chevere\Tests\Workflow\_resources\src;

use Chevere\Action\Action;
use Chevere\Dependent\Dependencies;
use Chevere\Dependent\Interfaces\DependenciesInterface;
use Chevere\Dependent\Interfaces\DependentInterface;
use Chevere\Dependent\Traits\DependentTrait;
use Chevere\Filesystem\Interfaces\DirInterface;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Parameter\Parameters;
use Chevere\Parameter\StringParameter;
use Chevere\Response\Interfaces\ResponseInterface;

class WorkflowRunnerTestDependentStep2 extends Action implements DependentInterface
{
    use DependentTrait;

    public function getDependencies(): DependenciesInterface
    {
        return new Dependencies(
            dir: DirInterface::class
        );
    }

    public function getParameters(): ParametersInterface
    {
        return new Parameters(
            foo: new StringParameter(),
            bar: new StringParameter()
        );
    }

    public function getResponseParameters(): ParametersInterface
    {
        return new Parameters(response2: new StringParameter());
    }

    public function run(ArgumentsInterface $arguments): ResponseInterface
    {
        return $this->getResponse(
            response2: $arguments->getString('foo') .
                    ' ^ ' . $arguments->getString('bar')
        );
    }
}
