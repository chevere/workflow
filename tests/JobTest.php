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

use function Chevere\DataStructure\vectorToArray;
use Chevere\Filesystem\Interfaces\PathInterface;
use Chevere\String\Exceptions\EmptyException;
use Chevere\Tests\_resources\src\TestActionNoParams;
use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Tests\_resources\src\TestActionObjectConflict;
use Chevere\Tests\_resources\src\TestActionParam;
use Chevere\Tests\_resources\src\TestActionParamStringAttribute;
use Chevere\Throwable\Errors\ArgumentCountError;
use Chevere\Throwable\Exceptions\BadMethodCallException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Job;
use function Chevere\Workflow\reference;
use function Chevere\Workflow\variable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JobTest extends TestCase
{
    public function testArgumentCountErrorEmpty(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage(
            TestActionNoParams::class
            . '::run requires 0 argument(s)'
        );
        new Job(
            new TestActionNoParams(),
            foo: 'extra',
        );
    }

    public function testArgumentCountErrorRequired(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage(
            TestActionParam::class
            . '::run requires 1 argument(s) [foo]'
        );
        new Job(
            new TestActionParam(),
        );
    }

    public function testWithArgumentCountError(): void
    {
        $job = new Job(new TestActionNoParams());
        $this->expectException(ArgumentCountError::class);
        $job->withArguments(
            foo: 'extra',
        );
    }

    public function testRawArguments(): void
    {
        $action = new TestActionParamStringAttribute();
        $success = [
            'foo' => 'foo',
        ];
        $job = new Job($action, ...$success);
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
        new Job($action, ...$fail);
    }

    public function testVariableArguments(): void
    {
        $action = new TestActionParamStringAttribute();
        $success = [
            'foo' => variable('foo'),
        ];
        $job = new Job($action, ...$success);
        $this->assertSame($action, $job->action());
        $this->assertSame($success, $job->arguments());
    }

    public function testReferenceArguments(): void
    {
        $action = new TestActionParamStringAttribute();
        $success = [
            'foo' => reference('job1', 'output'),
        ];
        $job = new Job($action, ...$success);
        $this->assertSame($action, $job->action());
        $this->assertSame($success, $job->arguments());
        $this->assertContains('job1', $job->dependencies());
    }

    public function testWithIsSync(): void
    {
        $job = new Job(new TestActionNoParams());
        $this->assertFalse($job->isSync());
        $jobWithSync = $job->withIsSync();
        $this->assertNotSame($job, $jobWithSync);
        $this->assertTrue($jobWithSync->isSync());
    }

    public function testWithDependencies(): void
    {
        $job = new Job(new TestActionNoParamsIntegerResponse());
        $this->assertSame([], vectorToArray($job->dependencies()));
        $job = $job->withDepends('foo', 'bar');
        $this->assertSame(['foo', 'bar'], vectorToArray($job->dependencies()));
        $job = $job->withDepends('foo', 'bar', 'wea');
        $this->assertSame(['foo', 'bar', 'wea'], vectorToArray($job->dependencies()));
    }

    public function testWithDependenciesOverflow(): void
    {
        $job = new Job(new TestActionNoParamsIntegerResponse());
        $this->assertSame([], vectorToArray($job->dependencies()));
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('Job dependencies must be unique');
        $this->expectExceptionMessage('repeated foo');
        $job->withDepends('bar', 'foo', 'foo');
    }

    public function testWithWrongDependencies(): void
    {
        $job = new Job(new TestActionNoParamsIntegerResponse());
        $this->assertSame([], vectorToArray($job->dependencies()));
        $this->expectException(EmptyException::class);
        $job->withDepends('');
    }

    public function testWithRunIfVariable(): void
    {
        $job = new Job(new TestActionNoParams());
        $variable = variable('wea');
        $job = $job->withRunIf($variable);
        $this->assertSame(
            [$variable],
            vectorToArray($job->runIf())
        );
        $this->expectException(OverflowException::class);
        $job->withRunIf($variable, $variable);
    }

    public function testWithRunIfReference(): void
    {
        $job = new Job(new TestActionNoParams());
        $reference = reference('jobN', 'parameter');
        $job = $job->withRunIf($reference);
        $this->assertSame(
            [$reference],
            vectorToArray($job->runIf())
        );
        $this->assertTrue($job->dependencies()->contains('jobN'));
        $this->expectException(OverflowException::class);
        $job->withRunIf($reference, $reference);
    }

    public function testWithMissingArgument(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage(
            'Missing argument(s) ['
            . PathInterface::class
            . ' path] for '
            . TestActionObjectConflict::class
        );
        new Job(
            new TestActionObjectConflict(),
            baz: 'baz',
            bar: variable('foo')
        );
    }
}
