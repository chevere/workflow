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

use Chevere\Tests\_resources\src\TestActionEmpty;
use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Tests\_resources\src\TestActionObjectConflict;
use Chevere\Tests\_resources\src\TestActionParamFooResponseBar;
use Chevere\Tests\_resources\src\TestActionParams;
use Chevere\Tests\_resources\src\TestActionParamsAlt;
use Chevere\Throwable\Exceptions\BadMethodCallException;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Job;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use function Chevere\Workflow\workflow;
use Chevere\Workflow\Workflow;
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase
{
    public function testEmpty(): void
    {
        $workflow = new Workflow(new Jobs());
        $this->assertCount(0, $workflow);
        $this->expectException(OutOfBoundsException::class);
        $workflow->getVar('not-found');
    }

    public function testMissingReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        workflow(
            zero: job(
                TestActionEmpty::class
            ),
            one: job(
                TestActionParamFooResponseBar::class,
                foo: '${null:value}'
            )
        );
    }

    public function testWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        workflow(
            one: job(
                TestActionNoParamsIntegerResponse::class,
            ),
            two: job(
                TestActionParams::class,
                foo: '${one:id}',
                bar: '${one:id}'
            )
        );
    }

    public function testConstruct(): void
    {
        $step = new Job(TestActionEmpty::class);
        $steps = new Jobs(step: $step);
        $workflow = new Workflow($steps);
        $this->assertCount(1, $workflow);
        $this->assertTrue($workflow->jobs()->has('step'));
        $this->assertSame(['step'], $workflow->jobs()->keys());
    }

    public function testWithAdded(): void
    {
        $step = new Job(TestActionEmpty::class);
        $steps = new Jobs(step: $step);
        $workflow = new Workflow($steps);
        $workflowWithAddedStep = $workflow->withAddedJob(step2: $step);
        $this->assertNotSame($workflow, $workflowWithAddedStep);
        $this->assertCount(2, $workflowWithAddedStep);
        $this->assertTrue($workflowWithAddedStep->jobs()->has('step'));
        $this->assertTrue($workflowWithAddedStep->jobs()->has('step2'));
        $this->assertSame(['step', 'step2'], $workflowWithAddedStep->jobs()->keys());
        $this->expectException(OverflowException::class);
        $workflowWithAddedStep->withAddedJob(step: $step);
    }
    
    public function testWithAddedStepWithArguments(): void
    {
        $step = new Job(
            TestActionParamFooResponseBar::class,
            foo: 'foo'
        );
        $workflow = (new Workflow(new Jobs(step: $step)))
            ->withAddedJob(name: $step);
        $this->assertSame($step, $workflow->jobs()->get('name'));
    }

    public function testWithReferencedParameters(): void
    {
        $workflow = new Workflow(
            new Jobs(
                step1: new Job(
                    TestActionParamFooResponseBar::class,
                    foo: '${foo}'
                )
            )
        );
        $this->assertTrue($workflow->vars()->has('${foo}'));
        $this->assertTrue($workflow->parameters()->has('foo'));
        $this->assertSame(['foo'], $workflow->getVar('${foo}'));
        $workflow = $workflow
            ->withAddedJob(
                step2: new Job(
                    TestActionParams::class,
                    foo: '${step1:bar}',
                    bar: '${foo}'
                )
            );
        $this->assertContains('step1', $workflow->jobs()->get('step2')->dependencies());
        $this->assertTrue($workflow->vars()->has('${foo}'));
        $this->assertTrue($workflow->vars()->has('${step1:bar}'));
        $this->assertTrue($workflow->parameters()->has('foo'));
        $this->assertSame(['foo'], $workflow->getVar('${foo}'));
        $this->assertSame(['step1', 'bar'], $workflow->getVar('${step1:bar}'));
        $this->expectException(InvalidArgumentException::class);
        $workflow->withAddedJob(
            step: new Job(
                TestActionParamFooResponseBar::class,
                foo: '${not:found}'
            )
        );
    }

    public function testConflictingParameterType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Incompatible declaration');
        $this->expectExceptionMessage('(argument@bar)');
        $this->expectExceptionMessage('Reference ${bar}');
        $this->expectExceptionMessage("doesn't match type");
        workflow(
            step1: job(
                TestActionParams::class,
                foo: '${foo}',
                bar: '${bar}'
            ),
            step2: job(
                TestActionParamsAlt::class,
                foo: '${foo}',
                bar: '${bar}'
            )
        );
    }

    public function testWithConflictingReferencedParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Incompatible declaration');
        $this->expectExceptionMessage('step2 (argument@foo)');
        $this->expectExceptionMessage('Reference ${step1:missing} not found');
        $this->expectExceptionMessage('not declared by step1');
        workflow(
            step1: job(
                TestActionParamFooResponseBar::class,
                foo: '${foo}'
            ),
            step2: job(
                TestActionParams::class,
                foo: '${step1:missing}',
                bar: '${foo}'
            )
        );
    }

    public function testWithConflictingTypeReferencedParameters(): void
    {
        $this->expectException(BadMethodCallException::class);
        workflow(
            step1: job(
                TestActionParamFooResponseBar::class,
                foo: '${foo}'
            ),
            step2: job(
                TestActionObjectConflict::class,
                baz: '${step1:bar}',
                bar: '${foo}'
            )
        );
    }
}
