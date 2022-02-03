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

namespace Chevere\Tests\Workflow;

use Chevere\Tests\Workflow\_resources\src\TaskTestStep0;
use Chevere\Tests\Workflow\_resources\src\TaskTestStep1;
use Chevere\Throwable\Errors\ArgumentCountError;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Workflow\Step;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class StepTest extends TestCase
{
    public function testInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Step('callable');
    }

    public function testUnexpectedValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        new Step(__CLASS__);
    }

    public function testArgumentCountError(): void
    {
        $this->expectException(ArgumentCountError::class);
        new Step(
            TaskTestStep0::class,
            foo: 'foo',
            bar: 'invalid extra argument'
        );
    }

    public function testWithArgumentCountError(): void
    {
        $this->expectException(ArgumentCountError::class);
        new Step(
            TaskTestStep0::class,
            foo: 'foo',
            bar: 'invalid extra argument'
        );
    }

    public function testConstruct(): void
    {
        $action = TaskTestStep1::class;
        $arguments = [
            'foo' => '1',
            'bar' => 123,
        ];
        $task = new Step($action);
        $this->assertSame($action, $task->action());
        $this->assertSame([], $task->arguments());
        $taskWithArgument = $task->withArguments(...$arguments);
        $this->assertNotSame($task, $taskWithArgument);
        $this->assertSame($arguments, $taskWithArgument->arguments());
        $task = new Step($action, ...$arguments);
        $this->assertSame($arguments, $task->arguments());
    }
}
