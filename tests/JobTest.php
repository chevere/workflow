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

use Chevere\Tests\_resources\src\TestAction;
use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Tests\_resources\src\TestActionParamsAlt;
use Chevere\Throwable\Errors\ArgumentCountError;
use Chevere\Throwable\Exception;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Job;
use function Chevere\Workflow\reference;
use function Chevere\Workflow\variable;
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
        $this->expectExceptionMessage('requires 0 arguments');
        $this->expectExceptionMessage('provided 2 foo, bar');
        new Job(
            TestAction::class,
            foo: 'foo',
            bar: 'invalid extra argument'
        );
    }

    public function testWithArgumentCountError(): void
    {
        $this->expectException(ArgumentCountError::class);
        new Job(
            TestAction::class,
            foo: 'foo',
            bar: 'invalid extra argument'
        );
    }

    public function testWithIsSync(): void
    {
        $action = TestActionParamsAlt::class;
        $job = new Job($action);
        $this->assertFalse($job->isSync());
        $jobWithSync = $job->withIsSync();
        $this->assertNotSame($job, $jobWithSync);
        $this->assertTrue($jobWithSync->isSync());
    }

    public function testConstruct(): void
    {
        $action = TestActionParamsAlt::class;
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
        $job = new Job(TestActionNoParamsIntegerResponse::class);
        $this->assertSame([], $job->dependencies());
        $job = $job->withDepends('foo', 'bar');
        $this->assertSame(['foo', 'bar'], $job->dependencies());
        $job = $job->withDepends('foo', 'bar', 'wea');
        $this->assertSame(['foo', 'bar', 'wea'], $job->dependencies());
    }

    public function testWithDependenciesOverflow(): void
    {
        $job = new Job(TestActionNoParamsIntegerResponse::class);
        $this->assertSame([], $job->dependencies());
        $this->expectException(OverflowException::class);
        $job->withDepends('foo', 'foo');
    }

    public function testWithWrongDeps(): void
    {
        $job = new Job(TestActionNoParamsIntegerResponse::class);
        $this->assertSame([], $job->dependencies());
        $this->expectException(Exception::class);
        $job->withDepends('');
    }

    public function testWithJobReference(): void
    {
        $job = new Job(
            TestActionParamsAlt::class,
            foo: reference('${step1:bar}'),
            bar: variable('${foo}')
        );
        $job = $job->withDepends('step1');
        $this->assertContains('step1', $job->dependencies());
    }
}
