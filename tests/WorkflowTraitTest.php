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

namespace Chevere\Tests;

use BadMethodCallException;
use Chevere\Tests\src\TestActionUseWorkflowTrait;
use PHPUnit\Framework\TestCase;

final class WorkflowTraitTest extends TestCase
{
    public function testBadMethodCall(): void
    {
        $this->expectException(BadMethodCallException::class);
        (new TestActionUseWorkflowTrait())->run();
    }

    public function testExecute(): void
    {
        $action = new TestActionUseWorkflowTrait();
        $action();
        $this->assertCount(0, $action->run());
    }
}
