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

use ArgumentCountError;
use BadMethodCallException;
use Chevere\Filesystem\Interfaces\PathInterface;
use Chevere\String\Exceptions\EmptyException;
use Chevere\Tests\src\TestActionNoParams;
use Chevere\Tests\src\TestActionNoParamsIntResponse;
use Chevere\Tests\src\TestActionObjectConflict;
use Chevere\Tests\src\TestActionParam;
use Chevere\Tests\src\TestActionParamStringRegex;
use Chevere\Workflow\Job;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\response;
use function Chevere\Workflow\variable;

final class JobTest extends TestCase
{
    public function testArgumentCountErrorEmpty(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage(
            '`'
            . TestActionNoParams::class
            . '::run` requires 0 argument(s)'
        );
        $action = new TestActionNoParams();
        new Job(
            $action,
            foo: 'extra',
        );
    }

    public function testArgumentCountErrorRequired(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage(
            '`'
            . TestActionParam::class
            . '::run` requires 1 argument(s) `[foo]`'
        );
        $action = new TestActionParam();
        new Job($action);
    }

    public function testWithArgumentCountError(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $this->expectException(ArgumentCountError::class);
        $job->withArguments(
            foo: 'extra',
        );
    }

    public function testRawArguments(): void
    {
        $action = new TestActionParamStringRegex();
        $success = [
            'foo' => 'foo',
        ];
        $job = new Job($action, false, ...$success);
        $this->assertSame($action, $job->action());
        $this->assertSame($success, $job->arguments());
        $success = [
            'foo' => 'bar',
        ];
        $jobWithArguments = $job->withArguments(...$success);
        $this->assertNotSame($job, $jobWithArguments);
        $this->assertSame($success, $jobWithArguments->arguments());
        $fail = [
            'foo' => '1',
        ];
        $this->expectException(InvalidArgumentException::class);
        new Job($action, false, ...$fail);
    }

    public function testVariableArguments(): void
    {
        $action = new TestActionParamStringRegex();
        $success = [
            'foo' => variable('foo'),
        ];
        $job = new Job($action, false, ...$success);
        $this->assertSame($action, $job->action());
        $this->assertSame($success, $job->arguments());
    }

    public function testReferenceResponseKey(): void
    {
        $action = new TestActionParamStringRegex();
        $success = [
            'foo' => response('job1', 'output'),
        ];
        $job = new Job($action, true, ...$success);
        $this->assertSame($action, $job->action());
        $this->assertSame($success, $job->arguments());
        $this->assertContains('job1', $job->dependencies());
    }

    public function testWithIsSync(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $this->assertNotTrue($job->isSync());
        $jobWithSync = $job->withIsSync();
        $this->assertNotSame($job, $jobWithSync);
        $this->assertTrue($jobWithSync->isSync());
        $jobWithSync = $job->withIsSync(true);
        $this->assertNotSame($job, $jobWithSync);
        $this->assertTrue($jobWithSync->isSync());
    }

    public function testWithDependencies(): void
    {
        $action = new TestActionNoParamsIntResponse();
        $job = new Job($action);
        $this->assertSame([], $job->dependencies()->toArray());
        $job = $job->withDepends('foo', 'bar');
        $this->assertSame(['foo', 'bar'], $job->dependencies()->toArray());
        $job = $job->withDepends('foo', 'bar', 'wea');
        $this->assertSame(['foo', 'bar', 'wea'], $job->dependencies()->toArray());
    }

    public function testWithDependenciesOverflow(): void
    {
        $action = new TestActionNoParamsIntResponse();
        $job = new Job($action);
        $this->assertSame([], $job->dependencies()->toArray());
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('Job dependencies must be unique');
        $this->expectExceptionMessage('repeated **foo**');
        $job->withDepends('bar', 'foo', 'foo');
    }

    public function testWithWrongDependencies(): void
    {
        $action = new TestActionNoParamsIntResponse();
        $job = new Job($action);
        $this->assertSame([], $job->dependencies()->toArray());
        $this->expectException(EmptyException::class);
        $job->withDepends('');
    }

    public function testWithRunIfVariable(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $variable = variable('wea');
        $job = $job->withRunIf($variable);
        $this->assertSame(
            [$variable],
            $job->runIf()->toArray()
        );
        $this->expectException(OverflowException::class);
        $job->withRunIf($variable, $variable);
    }

    public function testWithRunIfReference(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $reference = response('jobN', 'parameter');
        $job = $job->withRunIf($reference);
        $this->assertSame(
            [$reference],
            $job->runIf()->toArray()
        );
        $this->assertTrue($job->dependencies()->contains('jobN'));
        $this->expectException(OverflowException::class);
        $job->withRunIf($reference, $reference);
    }

    public function testWithMissingArgument(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage(
            'Missing argument(s) [`'
            . PathInterface::class
            . ' path`] for `'
            . TestActionObjectConflict::class
            . '`'
        );
        new Job(
            new TestActionObjectConflict(),
            baz: 'baz',
            bar: variable('foo')
        );
    }
}
