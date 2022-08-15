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
use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Tests\_resources\src\TestActionParamFooResponseBar;
use Chevere\Tests\_resources\src\TestActionParams;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use function Chevere\Workflow\reference;
use function Chevere\Workflow\variable;
use PHPUnit\Framework\TestCase;

final class JobsTest extends TestCase
{
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
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/undeclared dependencies\: j0$/');
        new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class)
                    ->withDepends('j0', 'j1'),
        );
    }

    public function testWithDependsOnPreviousChain(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class),
            j3: job(TestActionNoParams::class)
                ->withDepends('j2')
                ->withDepends('j1'),
        );
        $this->assertSame(
            [
                ['j1', 'j2'],
                ['j3'],
            ],
            $jobs->graph()
        );
    }

    public function testWithDependsOnPreviousFunction(): void
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

    public function testMissingReference(): void
    {
        $this->expectException(OutOfBoundsException::class);
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
        $this->expectExceptionMessage('Reference ${one:id} is of type integer, parameter foo expects string on job two');
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

    public function testWithAdded(): void
    {
        $jobs = new Jobs();
        $this->assertFalse($jobs->has('j1'));
        $withAdded = $jobs->withAdded(
            j1: job(TestActionNoParams::class),
        );
        $this->assertNotSame($jobs, $withAdded);
        $this->assertTrue($withAdded->has('j1'));
    }

    public function testWithRunIfUndeclaredJob(): void
    {
        $this->expectException(OutOfBoundsException::class);
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
        $this->expectException(OutOfBoundsException::class);
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
        $this->expectExceptionMessage('Reference ${j1:id} must be of type boolean');
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
}
