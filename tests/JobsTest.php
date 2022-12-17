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

use Chevere\Tests\_resources\src\TestActionNoParams;
use Chevere\Tests\_resources\src\TestActionNoParamsBooleanResponses;
use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Tests\_resources\src\TestActionParamFooResponseBar;
use Chevere\Tests\_resources\src\TestActionParams;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\OutOfRangeException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Job;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use function Chevere\Workflow\reference;
use function Chevere\Workflow\variable;
use PHPUnit\Framework\TestCase;

final class JobsTest extends TestCase
{
    public function testConstruct(): void
    {
        $jobs = new Jobs();
        $this->assertSame([], $jobs->keys());
        $this->assertSame([], iterator_to_array($jobs->getIterator()));
        $this->expectException(OutOfRangeException::class);
        $jobs->get('j1');
    }

    public function testConstructWithJob(): void
    {
        $j1 = new Job(TestActionNoParams::class);
        $jobs = new Jobs(
            j1: $j1
        );
        $this->assertSame(['j1'], $jobs->keys());
        $this->assertSame([
            'j1' => $j1,
        ], iterator_to_array($jobs->getIterator()));
    }

    public function testWithAdded(): void
    {
        $j1 = job(TestActionNoParams::class);
        $jobs = new Jobs();
        $this->assertFalse($jobs->has('j1'));
        $withAdded = $jobs->withAdded(
            j1: $j1,
        );
        $this->assertNotSame($jobs, $withAdded);
        $this->assertTrue($withAdded->has('j1'));
        $this->assertSame(['j1'], $withAdded->keys());
        $this->assertSame([
            'j1' => $j1,
        ], iterator_to_array($withAdded->getIterator()));
        $this->expectException(OverflowException::class);
        $withAdded->withAdded(j1: $j1);
    }

    public function testAsync(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class),
        );
        $this->assertSame(
            [
                ['j1', 'j2'],
            ],
            $jobs->graph()
        );
    }

    public function testSync(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class)->withIsSync(),
            j2: job(TestActionNoParams::class)->withIsSync(),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2'],
            ],
            $jobs->graph()
        );
    }

    public function testWithDependsOnJob(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class)->withDepends('j1'),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2'],
            ],
            $jobs->graph()
        );
    }

    public function testWithDependsMissing(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessageMatches('/undeclared dependencies\: j0$/');
        new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class)
                ->withDepends('j0', 'j1'),
        );
    }

    public function testWithDependsOnPreviousMultiple(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class),
            j3: job(TestActionNoParams::class)
                ->withDepends('j2', 'j1'),
        );
        $this->assertSame(
            [
                ['j1', 'j2'],
                ['j3'],
            ],
            $jobs->graph()
        );
    }

    public function testWithDependsOnPreviousSingle(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class)
                ->withDepends('j1'),
            j3: job(TestActionNoParams::class)
                ->withDepends('j2'),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2'],
                ['j3'],
            ],
            $jobs->graph()
        );
    }

    public function testWithDependsMix(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class),
            j3: job(TestActionNoParams::class)
                ->withDepends('j1', 'j2'),
            j4: job(TestActionNoParams::class),
            j5: job(TestActionNoParams::class)
                ->withDepends('j4'),
            j6: job(TestActionNoParams::class)
                ->withDepends('j5'),
        );
        $this->assertSame(
            [
                ['j1', 'j2', 'j4'],
                ['j3', 'j5'],
                ['j6'],
            ],
            $jobs->graph()
        );
    }

    public function testWithReference(): void
    {
        $jobs = new Jobs(
            one: job(
                TestActionParamFooResponseBar::class,
                foo: 'foo'
            ),
            two: job(
                TestActionParamFooResponseBar::class,
                foo: reference('one', 'bar')
            )
        );
        $this->assertSame(['one:bar', 'two:bar'], $jobs->references()->keys());
    }

    public function testMissingReference(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Job two has undeclared dependencies: zero');
        new Jobs(
            one: job(
                TestActionNoParams::class
            ),
            two: job(
                TestActionParamFooResponseBar::class,
                foo: reference('zero', 'key')
            )
        );
    }

    public function testWrongReferenceType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Reference one:id is of type integer, parameter foo expects string on job two');
        new Jobs(
            one: job(
                TestActionNoParamsIntegerResponse::class,
            ),
            two: job(
                TestActionParams::class,
                foo: reference('one', 'id'),
                bar: reference('one', 'id')
            )
        );
    }

    public function testWithRunIfUndeclaredJob(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Job job not found');
        new Jobs(
            j1: job(TestActionNoParams::class)
                ->withRunIf(
                    reference('job', 'parameter')
                ),
        );
    }

    public function testWithRunIfUndeclaredJobKey(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Parameter parameter not found');
        new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class)
                ->withRunIf(
                    reference('j1', 'parameter')
                ),
        );
    }

    public function testWithRunIfInvalidJobKeyType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Reference j1:id must be of type boolean');
        new Jobs(
            j1: job(TestActionNoParamsIntegerResponse::class),
            j2: job(TestActionNoParams::class)
                ->withRunIf(
                    reference('j1', 'id')
                ),
        );
    }

    public function testWithRunIfInvalidVariableType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Variable theFoo (previously declared as string) is not of type boolean at job j2');
        new Jobs(
            j1: job(
                TestActionParams::class,
                foo: variable('theFoo'),
                bar: 'bar'
            ),
            j2: job(TestActionNoParams::class)
                ->withRunIf(
                    variable('theFoo')
                ),
        );
    }

    public function testWithRunIfVariable(): void
    {
        $name = 'the_variable';
        $jobs = new Jobs(
            j1: job(
                TestActionNoParams::class,
            )
                ->withRunIf(
                    variable($name)
                ),
        );
        $this->assertTrue($jobs->variables()->has($name));
        $this->assertSame('boolean', $jobs->variables()->get($name)->primitive());
    }

    public function testWithRunIfReference(): void
    {
        $true = reference('j1', 'true');
        $false = reference('j1', 'false');
        $jobs = new Jobs(
            j1: job(
                TestActionNoParamsBooleanResponses::class,
            ),
            j2: job(
                TestActionNoParamsBooleanResponses::class,
            )->withRunIf($true, $false),
            j3: job(
                TestActionNoParams::class,
            )->withRunIf($false, $true),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2', 'j3'],
            ],
            $jobs->graph()
        );
        $this->assertTrue(
            $jobs->references()->has($true->__toString(), $false->__toString())
        );
        $j4 = job(TestActionNoParams::class)
            ->withRunIf(reference('j5', 'missing'));
        $this->expectException(OutOfRangeException::class);
        $jobs->withAdded(j4:$j4);
    }
}
