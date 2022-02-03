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

namespace Chevere\Tests\Pega;

use Chevere\Pluggable\Plug\Hook\HooksQueue;
use Chevere\Pluggable\Plug\Hook\HooksRunner;
use Chevere\Tests\Controller\_resources\src\ControllerTestController;
use Chevere\Tests\Controller\_resources\src\ControllerTestControllerDispatchAttribute;
use Chevere\Tests\Controller\_resources\src\ControllerTestControllerRelationAttribute;
use Chevere\Tests\Workflow\_resources\src\ControllerTestControllerRelationWorkflowAttribute;
use Chevere\Tests\Workflow\_resources\src\ControllerTestControllerRelationWorkflowAttributeError;
use Chevere\Tests\Controller\_resources\src\ControllerTestInvalidController;
use Chevere\Tests\Controller\_resources\src\ControllerTestModifyParamConflictHook;
use Chevere\Tests\Controller\_resources\src\ControllerTestModifyParamHook;
use Chevere\Tests\Workflow\_resources\src\WorkflowTestProvider;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Type\Type;
use PHPUnit\Framework\TestCase;

final class ControllerTest extends TestCase
{
    public function testControllerRelationWorkflowAttribute(): void
    {
        $controller = new ControllerTestControllerRelationWorkflowAttribute();
        $this->assertSame(WorkflowTestProvider::class, $controller->relation());
    }

    public function testControllerRelationWorkflowAttributeError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ControllerTestControllerRelationWorkflowAttributeError();
    }
}
