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
use Chevere\Tests\_resources\src\TestActionNoParams;
use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Tests\_resources\src\TestActionObjectConflict;
use Chevere\Tests\_resources\src\TestActionParams;
use Chevere\Tests\_resources\src\TestActionParamsAlt;
use Chevere\Throwable\Errors\ArgumentCountError;
use Chevere\Throwable\Exception;
use Chevere\Throwable\Exceptions\BadMethodCallException;
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
            TestActionNoParams::class,
            foo: 'foo',
            bar: 'invalid extra argument'
        );
    }

    public function testWithArgumentCountError(): void
    {
        $this->expectException(ArgumentCountError::class);
        new Job(
            TestActionNoParams::class,
            foo: 'foo',
            bar: 'invalid extra argument'
        );
    }

    public function testConstruct(): void
    {
        $action = TestActionParamsAlt::class;
        $parameters = [
            'foo' => variable('foo'),
            'bar' => variable('bar'),
        ];
        $arguments = [
            'foo' => '1',
            'bar' => 123,
        ];
        $job = new Job($action, ...$parameters);
        $this->assertSame($action, $job->action());
        $this->assertEquals(new $action(), $job->getAction());
        $this->assertSame($parameters, $job->arguments());
        $taskWithArgument = $job->withArguments(...$arguments);
        $this->assertNotSame($job, $taskWithArgument);
        $this->assertSame($arguments, $taskWithArgument->arguments());
        $job = new Job($action, ...$arguments);
        $this->assertSame($arguments, $job->arguments());
    }

    public function testWithIsSync(): void
    {
        $action = TestActionNoParams::class;
        $job = new Job($action);
        $this->assertFalse($job->isSync());
        $jobWithSync = $job->withIsSync();
        $this->assertNotSame($job, $jobWithSync);
        $this->assertTrue($jobWithSync->isSync());
    }

    public function testWithDependencies(): void
    {
        $job = new Job(TestActionNoParamsIntegerResponse::class);
        $this->assertSame([], vectorToArray($job->dependencies()));
        $job = $job->withDepends('foo', 'bar');
        $this->assertSame(['foo', 'bar'], vectorToArray($job->dependencies()));
        $job = $job->withDepends('foo', 'bar', 'wea');
        $this->assertSame(['foo', 'bar', 'wea'], vectorToArray($job->dependencies()));
    }

    public function testWithDependenciesOverflow(): void
    {
        $job = new Job(TestActionNoParamsIntegerResponse::class);
        $this->assertSame([], vectorToArray($job->dependencies()));
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('Job dependencies must be unique');
        $this->expectExceptionMessage('repeated foo');
        $job->withDepends('bar', 'foo', 'foo');
    }

    public function testWithWrongDepends(): void
    {
        $job = new Job(TestActionNoParamsIntegerResponse::class);
        $this->assertSame([], vectorToArray($job->dependencies()));
        $this->expectException(Exception::class);
        $job->withDepends('');
    }

    public function testWithJobReference(): void
    {
        $job = new Job(
            TestActionParams::class,
            foo: reference('step1', 'bar'),
            bar: reference('step1', 'bar')
        );
        $this->assertContains('step1', $job->dependencies());
    }

    public function testWithRunIfVariable(): void
    {
        $job = new Job(TestActionNoParams::class);
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
        $job = new Job(TestActionNoParams::class);
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
        $this->expectExceptionMessage('Missing argument(s) [' . PathInterface::class . ' path] for ' . TestActionObjectConflict::class);
        new Job(
            TestActionObjectConflict::class,
            baz: reference(job: 'step1', parameter: 'bar'),
            bar: variable('foo')
        );
    }
}
