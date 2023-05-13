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

use Chevere\Action\ActionName;
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
        $actionName = new ActionName(TestActionNoParams::class);
        new Job(
            $actionName,
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
        $actionName = new ActionName(TestActionParam::class);
        new Job($actionName);
    }

    public function testWithArgumentCountError(): void
    {
        $actionName = new ActionName(TestActionNoParams::class);
        $job = new Job($actionName);
        $this->expectException(ArgumentCountError::class);
        $job->withArguments(
            foo: 'extra',
        );
    }

    public function testRawArguments(): void
    {
        $actionName = new ActionName(TestActionParamStringAttribute::class);
        $success = [
            'foo' => 'foo',
        ];
        $job = new Job($actionName, false, ...$success);
        $this->assertSame($actionName, $job->actionName());
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
        new Job($actionName, false, ...$fail);
    }

    public function testVariableArguments(): void
    {
        $actionName = new ActionName(TestActionParamStringAttribute::class);
        $success = [
            'foo' => variable('foo'),
        ];
        $job = new Job($actionName, false, ...$success);
        $this->assertSame($actionName, $job->actionName());
        $this->assertSame($success, $job->arguments());
    }

    public function testReferenceArguments(): void
    {
        $actionName = new ActionName(TestActionParamStringAttribute::class);
        $success = [
            'foo' => reference('job1', 'output'),
        ];
        $job = new Job($actionName, true, ...$success);
        $this->assertSame($actionName, $job->actionName());
        $this->assertSame($success, $job->arguments());
        $this->assertContains('job1', $job->dependencies());
    }

    public function testWithIsSync(): void
    {
        $actionName = new ActionName(TestActionNoParams::class);
        $job = new Job($actionName);
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
        $actionName = new ActionName(TestActionNoParamsIntegerResponse::class);
        $job = new Job($actionName);
        $this->assertSame([], $job->dependencies()->toArray());
        $job = $job->withDepends('foo', 'bar');
        $this->assertSame(['foo', 'bar'], $job->dependencies()->toArray());
        $job = $job->withDepends('foo', 'bar', 'wea');
        $this->assertSame(['foo', 'bar', 'wea'], $job->dependencies()->toArray());
    }

    public function testWithDependenciesOverflow(): void
    {
        $actionName = new ActionName(TestActionNoParamsIntegerResponse::class);
        $job = new Job($actionName);
        $this->assertSame([], $job->dependencies()->toArray());
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('Job dependencies must be unique');
        $this->expectExceptionMessage('repeated foo');
        $job->withDepends('bar', 'foo', 'foo');
    }

    public function testWithWrongDependencies(): void
    {
        $actionName = new ActionName(TestActionNoParamsIntegerResponse::class);
        $job = new Job($actionName);
        $this->assertSame([], $job->dependencies()->toArray());
        $this->expectException(EmptyException::class);
        $job->withDepends('');
    }

    public function testWithRunIfVariable(): void
    {
        $actionName = new ActionName(TestActionNoParams::class);
        $job = new Job($actionName);
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
        $actionName = new ActionName(TestActionNoParams::class);
        $job = new Job($actionName);
        $reference = reference('jobN', 'parameter');
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
            'Missing argument(s) ['
            . PathInterface::class
            . ' path] for '
            . TestActionObjectConflict::class
        );
        new Job(
            new ActionName(TestActionObjectConflict::class),
            baz: 'baz',
            bar: variable('foo')
        );
    }
}
