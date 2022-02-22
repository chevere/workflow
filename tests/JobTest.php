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

use Chevere\Tests\_resources\src\TaskTestStep0;
use Chevere\Tests\_resources\src\TaskTestStep1;
use Chevere\Tests\_resources\src\TestAction;
use Chevere\Tests\_resources\src\WorkflowTestJob2;
use Chevere\Throwable\Errors\ArgumentCountError;
use Chevere\Throwable\Exception;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Job;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class JobTest extends TestCase
{
    public function testInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Job('callable');
    }

    public function testUnexpectedValue(): void
    {
        $this->expectException(UnexpectedValueException::class);
        new Job(__CLASS__);
    }

    public function testArgumentCountError(): void
    {
        $this->expectException(ArgumentCountError::class);
        new Job(
            TaskTestStep0::class,
            foo: 'foo',
            bar: 'invalid extra argument'
        );
    }

    public function testWithArgumentCountError(): void
    {
        $this->expectException(ArgumentCountError::class);
        new Job(
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
        $job = new Job($action);
        $this->assertSame($action, $job->action());
        $this->assertSame([], $job->arguments());
        $taskWithArgument = $job->withArguments(...$arguments);
        $this->assertNotSame($job, $taskWithArgument);
        $this->assertSame($arguments, $taskWithArgument->arguments());
        $job = new Job($action, ...$arguments);
        $this->assertSame($arguments, $job->arguments());
    }

    public function testWithDependencies(): void
    {
        $job = new Job(TestAction::class);
        $this->assertSame([], $job->dependencies());
        $job = $job->withDepends('foo', 'bar');
        $this->assertSame(['foo', 'bar'], $job->dependencies());
    }

    public function testWithDependenciesOverflow(): void
    {
        $job = new Job(TestAction::class);
        $this->assertSame([], $job->dependencies());
        $this->expectException(OverflowException::class);
        $job->withDepends('foo', 'foo');
    }

    public function testWithWrongDeps(): void
    {
        $job = new Job(TestAction::class);
        $this->assertSame([], $job->dependencies());
        $this->expectException(Exception::class);
        $job->withDepends('');
    }

    public function testWithJobReference(): void
    {
        $job = new Job(
            WorkflowTestJob2::class,
            foo: '${step1:bar}',
            bar: '${foo}'
        );
        $job = $job->withDepends('step1');
        $this->assertContains('step1', $job->dependencies());
    }
}
