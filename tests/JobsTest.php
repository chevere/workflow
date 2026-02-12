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

use Chevere\Action\Action;
use Chevere\Parameter\Attributes\PInt;
use Chevere\Parameter\Attributes\PReturn;
use Chevere\Parameter\Interfaces\BoolParameterInterface;
use Chevere\Tests\src\TestActionIntParamPReturn;
use Chevere\Tests\src\TestActionNoParams;
use Chevere\Tests\src\TestActionNoParamsArrayIntResponse;
use Chevere\Tests\src\TestActionNoParamsBoolResponses;
use Chevere\Tests\src\TestActionParamFooResponse1;
use Chevere\Tests\src\TestActionParamFooResponseBar;
use Chevere\Tests\src\TestActionParams;
use Chevere\Workflow\Exceptions\JobsException;
use Chevere\Workflow\Jobs;
use OutOfBoundsException;
use OverflowException;
use PHPUnit\Framework\TestCase;
use TypeError;
use function Chevere\Parameter\int;
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
        $j1 = async(new TestActionNoParams());
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
        $j1 = async(new TestActionNoParams());
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
            j1: async(new TestActionNoParams()),
            j2: async(new TestActionNoParams()),
        );
        $this->assertSame(
            [
                ['j1', 'j2'],
            ],
            $jobs->graph()->toArray()
        );
    }

    public function testSync(): void
    {
        $jobs = new Jobs(
            j1: sync(new TestActionNoParams()),
            j2: sync(new TestActionNoParams()),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2'],
            ],
            $jobs->graph()->toArray()
        );
    }

    public function testWithDependsOnJob(): void
    {
        $jobs = new Jobs(
            j1: async(new TestActionNoParams()),
            j2: async(new TestActionNoParams())->withDepends('j1'),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2'],
            ],
            $jobs->graph()->toArray()
        );
    }

    public function testWithDependsMissing(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/undeclared dependencies\: `j0`$/');
        new Jobs(
            j1: async(new TestActionNoParams()),
            j2: async(new TestActionNoParams())
                ->withDepends('j0', 'j1'),
        );
    }

    public function testWithDependsOnPreviousMultiple(): void
    {
        $jobs = new Jobs(
            j1: async(new TestActionNoParams()),
            j2: async(new TestActionNoParams()),
            j3: async(new TestActionNoParams())
                ->withDepends('j2', 'j1'),
        );
        $this->assertSame(
            [
                ['j1', 'j2'],
                ['j3'],
            ],
            $jobs->graph()->toArray()
        );
    }

    public function testWithDependsOnPreviousSingle(): void
    {
        $jobs = new Jobs(
            j1: async(new TestActionNoParams()),
            j2: async(new TestActionNoParams())
                ->withDepends('j1'),
            j3: async(new TestActionNoParams())
                ->withDepends('j2'),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2'],
                ['j3'],
            ],
            $jobs->graph()->toArray()
        );
    }

    public function testWithDependsMix(): void
    {
        $jobs = new Jobs(
            j1: async(new TestActionNoParams()),
            j2: async(new TestActionNoParams()),
            j3: async(new TestActionNoParams())
                ->withDepends('j1', 'j2'),
            j4: async(new TestActionNoParams()),
            j5: async(new TestActionNoParams())
                ->withDepends('j4'),
            j6: async(new TestActionNoParams())
                ->withDepends('j5'),
        );
        $this->assertSame(
            [
                ['j1', 'j2', 'j4'],
                ['j3', 'j5'],
                ['j6'],
            ],
            $jobs->graph()->toArray()
        );
    }

    public function testWithReferenceShouldFail(): void
    {
        // previous: InvalidArgumentException
        $this->expectException(JobsException::class);
        $this->expectExceptionMessage(
            <<<STRING
            [two]: Response **one:bar** conflict at parameter **foo**: Expected regex `/^.*$/s`, provided `/^bar$/`
            STRING
        );
        new Jobs(
            one: async(
                new TestActionParamFooResponseBar(),
                foo: 'bar'
            ),
            two: async(
                new TestActionParamFooResponse1(),
                foo: response('one', 'bar')
            )
        );
    }

    public function testMissingReference(): void
    {
        // previous: OutOfBoundsException
        $this->expectException(JobsException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [two]: Response **zero:key** not found
            PLAIN
        );
        new Jobs(
            one: async(
                new TestActionNoParams()
            ),
            two: async(
                new TestActionParamFooResponseBar(),
                foo: response('zero', 'key')
            )
        );
    }

    public function testWrongReferenceType(): void
    {
        // previous: TypeError
        $this->expectException(JobsException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [two]: Response **one:id** is of type `int`, parameter **foo** expects `string`
            PLAIN
        );
        new Jobs(
            one: async(
                new TestActionNoParamsArrayIntResponse(),
            ),
            two: async(
                new TestActionParams(),
                foo: response('one', 'id'),
                bar: response('one', 'id')
            )
        );
    }

    public function testWithRunIfUndeclaredJob(): void
    {
        $this->expectException(OutOfBoundsException::class);
        new Jobs(
            j1: async(new TestActionNoParams())
                ->withRunIf(
                    response('job', 'parameter')
                ),
        );
    }

    public function testWithRunIfUndeclaredJobResponseKey(): void
    {
        $this->expectException(OutOfBoundsException::class);
        new Jobs(
            j1: async(new TestActionNoParams()),
            j2: async(new TestActionNoParams())
                ->withRunIf(
                    response('j1', '404')
                ),
        );
    }

    public function testWithRunIfInvalidJobKeyType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Response **j1:id** must be of type `bool`');
        new Jobs(
            j1: async(new TestActionNoParamsArrayIntResponse()),
            j2: async(new TestActionNoParams())
                ->withRunIf(
                    response('j1', 'id')
                ),
        );
    }

    public function testWithRunIfInvalidVariableType(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Variable **theFoo** (previously declared as `string`) is not of type `bool` at Job **j2**');
        new Jobs(
            j1: async(
                new TestActionParams(),
                foo: variable('theFoo'),
                bar: 'bar'
            )
                ->withRunIf(
                    variable('true')
                ),
            j2: async(
                new TestActionNoParams()
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
                new TestActionNoParams(),
            )
                ->withRunIf(
                    variable($name)
                ),
        );
        $this->assertTrue($jobs->variables()->has($name));
        $this->assertInstanceOf(BoolParameterInterface::class, $jobs->variables()->get($name));
    }

    public function testWithRunIfReference(): void
    {
        $true = response('j1', 'true');
        $false = response('j1', 'false');
        $jobs = new Jobs(
            j1: async(
                new TestActionNoParamsBoolResponses(),
            ),
            j2: async(
                new TestActionNoParamsBoolResponses(),
            )->withRunIf($true, $false),
            j3: async(
                new TestActionNoParams(),
            )->withRunIf($false, $true),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2', 'j3'],
            ],
            $jobs->graph()->toArray()
        );
        $this->assertTrue(
            $jobs->references()->has($true->__toString(), $false->__toString())
        );
        $j4 = async(new TestActionNoParams())
            ->withRunIf(response('j5', 'missing'));
        $this->expectException(OutOfBoundsException::class);
        $jobs->withAdded(j4: $j4);
    }

    public function testWithRunIfCallable(): void
    {
        $callable = fn () => true;
        $jobs = new Jobs(
            j1: async(new TestActionNoParams()),
            j2: async(new TestActionNoParams())
                ->withRunIf($callable),
        );
        $runIf = $jobs->get('j2')->runIf()->get(0);
        $this->assertSame($callable, $runIf);
    }

    public function testWithMissingReference(): void
    {
        // previous: OutOfBoundsException
        $this->expectException(JobsException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [job2]: Response **job1:missing** not found
            PLAIN
        );
        new Jobs(
            job1: async(
                new TestActionParamFooResponseBar(),
                foo: 'bar'
            ),
            job2: async(
                new TestActionParamFooResponseBar(),
                foo: response('job1', 'missing'),
            )
        );
    }

    public function testWithInvalidReference(): void
    {
        $this->expectException(JobsException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [job2]: Invalid response reference **job1:missing** as job **job1** doesn't define return rules implementing Chevere\Parameter\Interfaces\ParametersAccessInterface interface
            PLAIN
        );
        new Jobs(
            job1: async(
                new TestActionNoParams(),
            ),
            job2: async(
                new TestActionParamFooResponseBar(),
                foo: response('job1', 'missing'),
            )
        );
    }

    public function testWithInvalidTypeReference(): void
    {
        // previous: TypeError
        $this->expectException(JobsException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [job2]: Response **job1:baz** is of type `float`, parameter **foo** expects `string`
            PLAIN
        );
        new Jobs(
            job1: async(
                new TestActionParamFooResponseBar(),
                foo: 'bar'
            ),
            job2: async(
                new TestActionParamFooResponseBar(),
                foo: response('job1', 'baz'),
            )
        );
    }

    public function testWithAttrOverride(): void
    {
        $this->expectException(JobsException::class);
        $this->expectExceptionMessage('[j2]: Response **j1** conflict at parameter **number**: Expected min value `1`, provided `8`');
        new Jobs(
            j1: async(
                new TestActionIntParamPReturn(),
                number: 1,
            ),
            j2: async(
                new TestActionIntParamPReturn(),
                number: response('j1')
            )
        );
    }

    public function testAttributeDrivenReference(): void
    {
        $this->expectException(JobsException::class);
        $this->expectExceptionMessage('[job2]: Response **job1** conflict at parameter **number**: Expected min value `1`, provided `-1`');
        new Jobs(
            job1: sync(
                new class() extends Action {
                    #[PReturn(
                        new PInt(min: -1)
                    )]
                    public function __invoke(
                        bool $isAnnual,
                        #[PInt(min: 0)]
                        int $recurring_price_month,
                        #[PInt(min: 0)]
                        int $recurring_price_year,
                    ): int {
                        return $isAnnual ? $recurring_price_year : $recurring_price_month;
                    }
                },
                isAnnual: variable('isAnnual'),
                recurring_price_year: 96,
                recurring_price_month: 10,
            ),
            job2: sync(
                new TestActionIntParamPReturn(),
                number: response('job1'), // int min -1, TestActionIntParamPReturn $number expects min 1
            ),
        );
    }
}
