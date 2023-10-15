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

use Chevere\Parameter\Interfaces\BooleanParameterInterface;
use Chevere\Tests\src\TestActionNoParams;
use Chevere\Tests\src\TestActionNoParamsBooleanResponses;
use Chevere\Tests\src\TestActionNoParamsIntegerResponse;
use Chevere\Tests\src\TestActionParamFooResponse1;
use Chevere\Tests\src\TestActionParamFooResponseBar;
use Chevere\Tests\src\TestActionParams;
use Chevere\Throwable\Errors\TypeError;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Jobs;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\async;
use function Chevere\Workflow\response;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\variable;

final class JobsTest extends TestCase
{
    public function testConstruct(): void
    {
        $jobs = new Jobs();
        $this->assertSame([], $jobs->keys());
        $this->assertSame([], iterator_to_array($jobs->getIterator()));
        $this->expectException(OutOfBoundsException::class);
        $jobs->get('j1');
    }

    public function testConstructWithJob(): void
    {
        $j1 = async(TestActionNoParams::class);
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
        $j1 = async(TestActionNoParams::class);
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
            j1: async(TestActionNoParams::class),
            j2: async(TestActionNoParams::class),
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
            j1: sync(TestActionNoParams::class),
            j2: sync(TestActionNoParams::class),
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
            j1: async(TestActionNoParams::class),
            j2: async(TestActionNoParams::class)->withDepends('j1'),
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
            j1: async(TestActionNoParams::class),
            j2: async(TestActionNoParams::class)
                ->withDepends('j0', 'j1'),
        );
    }

    public function testWithDependsOnPreviousMultiple(): void
    {
        $jobs = new Jobs(
            j1: async(TestActionNoParams::class),
            j2: async(TestActionNoParams::class),
            j3: async(TestActionNoParams::class)
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
            j1: async(TestActionNoParams::class),
            j2: async(TestActionNoParams::class)
                ->withDepends('j1'),
            j3: async(TestActionNoParams::class)
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
            j1: async(TestActionNoParams::class),
            j2: async(TestActionNoParams::class),
            j3: async(TestActionNoParams::class)
                ->withDepends('j1', 'j2'),
            j4: async(TestActionNoParams::class),
            j5: async(TestActionNoParams::class)
                ->withDepends('j4'),
            j6: async(TestActionNoParams::class)
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

    public function testWithReferenceShouldFail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            <<<STRING
            Reference one:bar conflict for parameter foo on Job two (Expected regex /^bar$/, provided /^.*$/)
            STRING
        );
        new Jobs(
            one: async(
                TestActionParamFooResponseBar::class,
                foo: 'bar'
            ),
            two: async(
                TestActionParamFooResponse1::class,
                foo: response('one', 'bar')
            )
        );
    }

    public function testMissingReference(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Reference zero:key not found at job two');
        new Jobs(
            one: async(
                TestActionNoParams::class
            ),
            two: async(
                TestActionParamFooResponseBar::class,
                foo: response('zero', 'key')
            )
        );
    }

    public function testWrongReferenceType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Reference one:id is of type integer, parameter foo expects string at job two');
        new Jobs(
            one: async(
                TestActionNoParamsIntegerResponse::class,
            ),
            two: async(
                TestActionParams::class,
                foo: response('one', 'id'),
                bar: response('one', 'id')
            )
        );
    }

    public function testWithRunIfUndeclaredJob(): void
    {
        $this->expectException(OutOfBoundsException::class);
        new Jobs(
            j1: async(TestActionNoParams::class)
                ->withRunIf(
                    response('job', 'parameter')
                ),
        );
    }

    public function testWithRunIfUndeclaredJobKey(): void
    {
        $this->expectException(OutOfBoundsException::class);
        new Jobs(
            j1: async(TestActionNoParams::class),
            j2: async(TestActionNoParams::class)
                ->withRunIf(
                    response('j1', 'parameter')
                ),
        );
    }

    public function testWithRunIfInvalidJobKeyType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Reference j1:id must be of type boolean');
        new Jobs(
            j1: async(TestActionNoParamsIntegerResponse::class),
            j2: async(TestActionNoParams::class)
                ->withRunIf(
                    response('j1', 'id')
                ),
        );
    }

    public function testWithRunIfInvalidVariableType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Variable theFoo (previously declared as string) is not of type boolean at Job j2');
        new Jobs(
            j1: async(
                TestActionParams::class,
                foo: variable('theFoo'),
                bar: 'bar'
            )
                ->withRunIf(
                    variable('true')
                ),
            j2: async(
                TestActionNoParams::class
            )
                ->withRunIf(
                    variable('true'),
                    variable('theFoo')
                ),
        );
    }

    public function testWithRunIfVariable(): void
    {
        $name = 'the_variable';
        $jobs = new Jobs(
            j1: async(
                TestActionNoParams::class,
            )
                ->withRunIf(
                    variable($name)
                ),
        );
        $this->assertTrue($jobs->variables()->has($name));
        $this->assertInstanceOf(BooleanParameterInterface::class, $jobs->variables()->get($name));
    }

    public function testWithRunIfReference(): void
    {
        $true = response('j1', 'true');
        $false = response('j1', 'false');
        $jobs = new Jobs(
            j1: async(
                TestActionNoParamsBooleanResponses::class,
            ),
            j2: async(
                TestActionNoParamsBooleanResponses::class,
            )->withRunIf($true, $false),
            j3: async(
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
        $j4 = async(TestActionNoParams::class)
            ->withRunIf(response('j5', 'missing'));
        $this->expectException(OutOfBoundsException::class);
        $jobs->withAdded(j4: $j4);
    }

    public function testWithMissingReference(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Reference job1:missing not found at job job2');
        new Jobs(
            job1: async(
                TestActionParamFooResponseBar::class,
                foo: 'bar'
            ),
            job2: async(
                TestActionParamFooResponseBar::class,
                foo: response('job1', 'missing'),
            )
        );
    }

    public function testWithInvalidTypeReference(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Reference job1:baz is of type float, parameter foo expects string at job job2');
        new Jobs(
            job1: async(
                TestActionParamFooResponseBar::class,
                foo: 'bar'
            ),
            job2: async(
                TestActionParamFooResponseBar::class,
                foo: response('job1', 'baz'),
            )
        );
    }
}
