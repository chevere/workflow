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
use Chevere\Filesystem\Interfaces\PathInterface;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Response\Interfaces\ResponseInterface;

class WorkflowTestStepDeps0 extends Action implements DependentInterface
{
    use DependentTrait;

    private PathInterface $path;

    public function getDependencies(): DependenciesInterface
    {
        return new Dependencies(path: PathInterface::class);
    }

    public function run(ArgumentsInterface $arguments): ResponseInterface
    {
        return $this->getResponse();
    }
}
