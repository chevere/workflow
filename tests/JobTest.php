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
use Chevere\Action\Action;
use Chevere\Parameter\Attributes\_int;
use Chevere\Parameter\Attributes\_return;
use Chevere\Tests\src\TestActionNoParams;
use Chevere\Tests\src\TestActionNoParamsArrayIntResponse;
use Chevere\Tests\src\TestActionObjectConflict;
use Chevere\Tests\src\TestActionParam;
use Chevere\Tests\src\TestActionParamStringRegex;
use Chevere\Tests\src\TestActionVariadic;
use Chevere\Tests\src\TestClassInvalidArgument;
use Chevere\Workflow\Interfaces\RetryPolicyInterface;
use Chevere\Workflow\Job;
use Chevere\Workflow\RetryPolicy;
use Closure;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use function Chevere\Parameter\int;
use function Chevere\Parameter\parameters;
use function Chevere\Parameter\string;
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
            . '::__invoke'
            . '` requires 0 argument(s)'
        );
        $action = new TestActionNoParams();
        new Job(
            $action,
            foo: 'extra',
        );
    }

    public function testArgumentCountErrorMissing(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            `Chevere\Tests\src\TestActionParam::__invoke` requires 1 argument(s) `[string \$foo]`
            PLAIN
        );
        $action = new TestActionParam();
        new Job(
            $action,
            foo: 'extra',
            nepe: 'extra'
        );
    }

    public function testArgumentCountErrorRequired(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Missing argument(s) [`string \$foo`] for `Chevere\Tests\src\TestActionParam`
            PLAIN
        );
        $action = new TestActionParam();
        new Job($action);
    }

    public function testArgumentCountErrorTooManyPositional(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage(
            '`' . TestActionParam::class . '::__invoke` requires 1 argument(s), but 2 provided'
        );
        $action = new TestActionParam();
        new Job($action, 'foo', 'extra');
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

    public function testCaller(): void
    {
        $action = TestActionNoParams::class;
        $job = new Job($action);
        $fileLine = __FILE__ . ':' . (__LINE__ - 1);
        $this->assertSame($fileLine, $job->caller()->__toString());
        $expectedFile = __DIR__ . '/src/TestReturnSyncActionNoParams.php';
        $expectedLine = 18; // Line where sync() is called
        $sync = require $expectedFile;
        $this->assertSame(
            "{$expectedFile}:{$expectedLine}",
            $sync->caller()->__toString()
        );
    }

    public function testRawArguments(): void
    {
        $action = new TestActionParamStringRegex();
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
        $this->expectExceptionMessage(
            <<<PLAIN
            Argument [foo]: Argument value provided `1` doesn't match the regex `/^foo|bar$/`
            PLAIN
        );
        new Job($action, ...$fail);
    }

    public function testVariableArguments(): void
    {
        $action = new TestActionParamStringRegex();
        $success = [
            'foo' => variable('foo'),
        ];
        $job = new Job($action, ...$success);
        $this->assertSame($action, $job->action());
        $this->assertSame($success, $job->arguments());
    }

    public function testReferenceResponseKey(): void
    {
        $action = new TestActionParamStringRegex();
        $success = [
            'foo' => response('job1', 'output'),
        ];
        $job = (new Job($action, ...$success))->withIsSync(true);
        $this->assertSame($action, $job->action());
        $this->assertSame($success, $job->arguments());
        $this->assertContains('job1', $job->dependencies());
    }

    public function testDependenciesUnique(): void
    {
        $job = new Job(
            function (string $foo, string $bar): void {
            },
            foo: response('job1'),
            bar: response('job1', 'a')
        );

        $this->assertSame(
            ['job1'],
            $job->dependencies()->toArray()
        );
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
        $action = new TestActionNoParamsArrayIntResponse();
        $job = new Job($action);
        $this->assertSame([], $job->dependencies()->toArray());
        $job = $job->withDepends('foo', 'bar');
        $this->assertSame(['foo', 'bar'], $job->dependencies()->toArray());
        $job = $job->withDepends('foo', 'bar', 'wea');
        $this->assertSame(['foo', 'bar', 'wea'], $job->dependencies()->toArray());
    }

    public function testWithDependenciesOverflow(): void
    {
        $action = new TestActionNoParamsArrayIntResponse();
        $job = new Job($action);
        $this->assertSame([], $job->dependencies()->toArray());
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('Job dependencies must be unique');
        $this->expectExceptionMessage('repeated **foo**');
        $job->withDepends('bar', 'foo', 'foo');
    }

    public function testWithWrongDependencies(): void
    {
        $action = new TestActionNoParamsArrayIntResponse();
        $job = new Job($action);
        $this->assertSame([], $job->dependencies()->toArray());
        $this->expectException(InvalidArgumentException::class);
        $job->withDepends('');
    }

    public function testWithRunIfVariable(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $variable = variable('wea');
        $with = $job->withRunIf($variable);
        $this->assertNotSame($job, $with);
        $this->assertSame(
            [$variable],
            $with->runIf()->toArray()
        );
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Condition `wea` is already defined
            PLAIN
        );
        $with->withRunIf($variable, $variable);
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
        $this->expectExceptionMessage(
            <<<PLAIN
            Condition `jobN:parameter` is already defined
            PLAIN
        );
        $job->withRunIf($reference, $reference);
    }

    public function testWithRunIfClosure(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $closure = function (): bool {
            return true;
        };
        $closureId = spl_object_id($closure);
        $job = $job->withRunIf($closure);
        $this->assertSame(
            [$closure],
            $job->runIf()->toArray()
        );
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Condition `callable#{$closureId}` is already defined
            PLAIN
        );
        $job->withRunIf($closure, $closure);
    }

    public function testWithRunIfBool(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $job = $job->withRunIf(true);
        $this->assertSame(
            [true],
            $job->runIf()->toArray()
        );
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Condition `bool#true` is already defined
            PLAIN
        );
        $job->withRunIf(true, true);
    }

    public function testWithRunIfNotVariable(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $variable = variable('wea');
        $with = $job->withRunIfNot($variable);
        $this->assertNotSame($job, $with);
        $this->assertSame(
            [$variable],
            $with->runIfNot()->toArray()
        );
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Condition `wea` is already defined
            PLAIN
        );
        $with->withRunIfNot($variable, $variable);
    }

    public function testWithRunIfNotReference(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $reference = response('jobN', 'parameter');
        $job = $job->withRunIfNot($reference);
        $this->assertSame(
            [$reference],
            $job->runIfNot()->toArray()
        );
        $this->assertTrue($job->dependencies()->contains('jobN'));
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Condition `jobN:parameter` is already defined
            PLAIN
        );
        $job->withRunIfNot($reference, $reference);
    }

    public function testWithRunIfNotClosure(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $closure = function (): bool {
            return true;
        };
        $closureId = spl_object_id($closure);
        $job = $job->withRunIfNot($closure);
        $this->assertSame(
            [$closure],
            $job->runIfNot()->toArray()
        );
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Condition `callable#{$closureId}` is already defined
            PLAIN
        );
        $job->withRunIfNot($closure, $closure);
    }

    public function testWithRunIfNotBool(): void
    {
        $action = new TestActionNoParams();
        $job = new Job($action);
        $job = $job->withRunIfNot(true);
        $this->assertSame(
            [true],
            $job->runIfNot()->toArray()
        );
        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Condition `bool#true` is already defined
            PLAIN
        );
        $job->withRunIfNot(true, true);
    }

    public function testWithMissingArgument(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage(
            'Missing argument(s) [`'
            . stdClass::class
            . ' $path`] for `'
            . TestActionObjectConflict::class
            . '`'
        );
        new Job(
            new TestActionObjectConflict(),
            baz: 'baz',
            bar: variable('foo')
        );
    }

    public function testWithClosureWithReturnType(): void
    {
        $closureFileLine = __FILE__ . ':' . (__LINE__ + 1);
        $closure = function (string $foo): int {
            return strlen($foo);
        };
        $job = new Job($closure, foo: 'bar');
        $this->assertSame($closure, $job->action());
        $this->assertEquals(int(), $job->return());
        $this->assertEquals(parameters(foo: string()), $job->parameters());
        $this->expectException(ArgumentCountError::class);
        $callerFileLine = __FILE__ . ':' . (__LINE__ + 6);
        $this->expectExceptionMessage(
            <<<PLAIN
            Missing argument(s) [`string \$foo`] for closure @ {$closureFileLine} in {$callerFileLine}
            PLAIN
        );
        new Job($closure);
    }

    public function testWithCallableMethod(): void
    {
        $class = new class() {
            public function callableMethod(string $foo): string
            {
                return $foo;
            }
        };
        $callable = [$class, 'callableMethod'];
        $job = new Job($callable, foo: 'bar');
        $this->assertInstanceOf(Closure::class, $job->action());
        $this->assertEquals(parameters(foo: string()), $job->parameters());
        $this->assertEquals(string(), $job->return());
    }

    public function testWithFunctionNameCallable(): void
    {
        $job = new Job('strlen', 'hello');
        $this->assertInstanceOf(Closure::class, $job->action());
        $this->assertEquals(int(), $job->return());
        $this->assertCount(1, $job->parameters());
    }

    public function testWithAnonymousClassMissingArgument(): void
    {
        $anonClassFileLine = __FILE__ . ':' . (__LINE__ + 1);
        $anon = new class() extends Action {
            public function __invoke(string $foo): array
            {
                return [];
            }
        };
        $job = new Job($anon, foo: 'bar');
        $this->assertSame($anon, $job->action());
        $this->expectException(ArgumentCountError::class);
        $callerFileLine = __FILE__ . ':' . (__LINE__ + 6);
        $this->expectExceptionMessage(
            <<<PLAIN
            Missing argument(s) [`string \$foo`] for anon class @ {$anonClassFileLine} in {$callerFileLine}
            PLAIN
        );
        new Job($anon);
    }

    public function testWithClosureWithoutReturnType(): void
    {
        $closure = function () {
            return 100;
        };
        $job = new Job($closure);
        $this->assertEquals('mixed', $job->return()->type()->typeHinting());
    }

    public function testVariadicDependencies(): void
    {
        $closure = function (string ...$bars): void {
        };
        $expected = [
            'bar1' => response('job1'),
            'bar2' => response('job1', 'id'),
        ];

        $job = new Job($closure, ...$expected);
        $this->assertSame($expected, $job->arguments());
        $this->assertSame(['job1'], $job->dependencies()->toArray());
    }

    public function testVariadicParameterValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Argument \[bar\]: .*must be of type int/');
        new Job(TestActionVariadic::class, bar1: 'not-an-int');
    }

    public function testVariadicPositionalArgumentsAreRecorded(): void
    {
        $job = new Job(TestActionVariadic::class, 'custom', 1, 2);
        $this->assertSame(
            [
                'custom',
                1,
                2,
            ],
            $job->arguments()
        );
    }

    public function testVariadicPositionalParameterValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Job(TestActionVariadic::class, 'custom', 'not-an-int');
    }

    public function testWithClosureWithVoidReturnType(): void
    {
        $closure = function (): void {};
        $job = new Job($closure);
        $this->assertEquals('null', $job->return()->type()->typeHinting());
    }

    public function testWithClosureAttributes(): void
    {
        $closure = #[_return(new _int(min: -1))] function (string $foo): int {
            return strlen($foo);
        };
        $job = new Job($closure, foo: 'bar');
        $this->assertSame($closure, $job->action());
        $this->assertEquals(int(min: -1), $job->return());
        $this->assertEquals(parameters(foo: string()), $job->parameters());
    }

    public function testRetryDefault(): void
    {
        $job = new Job(TestActionNoParams::class);
        $this->assertEquals(new RetryPolicy(), $job->retryPolicy());
    }

    #[DataProvider('dataProviderRetryDefinedValues')]
    public function testRetryDefinedValues(RetryPolicyInterface $expected, array $arguments): void
    {
        $job = (new Job(TestActionNoParams::class));
        $with = $job->withRetry(...$arguments);
        $this->assertNotSame($job, $with);
        $this->assertEquals($expected, $with->retryPolicy());
    }

    public static function dataProviderRetryDefinedValues(): array
    {
        return [
            [
                new RetryPolicy(),
                [],
            ],
            [
                new RetryPolicy(timeout: 10),
                [
                    'timeout' => 10,
                    'maxAttempts' => 1,
                    'delay' => 0,
                ],
            ],
            [
                new RetryPolicy(maxAttempts: 5),
                [
                    'timeout' => 0,
                    'maxAttempts' => 5,
                    'delay' => 0,
                ],
            ],
            [
                new RetryPolicy(delay: 3),
                [
                    'timeout' => 0,
                    'maxAttempts' => 1,
                    'delay' => 3,
                ],
            ],
            [
                new RetryPolicy(timeout: 10, maxAttempts: 5, delay: 3),
                [
                    'timeout' => 10,
                    'maxAttempts' => 5,
                    'delay' => 3,
                ],
            ],
        ];
    }

    public static function dataProviderInvalidArgument(): array
    {
        require_once __DIR__ . '/src/TestFunctions.php';

        return [
            'closure' => [
                function (#[_int(min: 2)] int $id = 1): void {
                },
            ],
            'anon class' => [
                new class() {
                    public function __invoke(#[_int(min: 2)] int $id = 1): void
                    {
                    }
                },
            ],
            'class callable array' => [
                [TestClassInvalidArgument::class, 'staticCallable'],
            ],
            'object callable array' => [
                (new class() {
                    public function __invoke(#[_int(min: 2)] int $id = 1): void
                    {
                    }
                }),
                '__invoke',
            ],
            'string callable' => [
                'Chevere\Tests\src\invalidArgumentFunction',
            ],
        ];
    }

    #[DataProvider('dataProviderInvalidArgument')]
    public function testActionInvalidArgument(mixed $action): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            Argument value provided `1` is less than `2`
            PLAIN
        );
        new Job($action);
    }
}
