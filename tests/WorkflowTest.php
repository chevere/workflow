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
use Chevere\Tests\_resources\src\TestActionObjectConflict;
use Chevere\Tests\_resources\src\TestActionParamFooResponseBar;
use Chevere\Tests\_resources\src\TestActionParams;
use Chevere\Throwable\Exceptions\BadMethodCallException;
use Chevere\Throwable\Exceptions\OutOfRangeException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Job;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use function Chevere\Workflow\reference;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;
use Chevere\Workflow\Workflow;
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase
{
    public function testConstruct(): void
    {
        $step = new Job(TestActionNoParams::class);
        $steps = new Jobs(step: $step);
        $workflow = new Workflow($steps);
        $this->assertCount(1, $workflow);
        $this->assertTrue($workflow->jobs()->has('step'));
        $this->assertSame(['step'], $workflow->jobs()->keys());
    }

    public function testWithAdded(): void
    {
        $step = new Job(TestActionNoParams::class);
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

    // public function testWithReferencedParameters(): void
    // {
    //     $workflow = new Workflow(
    //         new Jobs(
    //             step1: new Job(
    //                 TestActionParamFooResponseBar::class,
    //                 foo: variable('foo')
    //             ),
    //         )
    //     );
    //     $this->assertTrue($workflow->jobs()->has('step1'));
    //     $this->assertTrue($workflow->jobs()->variables()->has('foo'));
    //     $this->assertTrue($workflow->parameters()->has('foo'));
    //     $workflow = $workflow
    //         ->withAddedJob(
    //             step2: new Job(
    //                 TestActionParams::class,
    //                 foo: reference(job: 'step1', parameter: 'bar'),
    //                 bar: variable('foo')
    //             )
    //         );
    //     $this->assertContains('step1', $workflow->jobs()->get('step2')->dependencies());
    //     $this->assertTrue($workflow->parameters()->has('foo'));
    //     $this->expectException(OutOfRangeException::class);
    //     $workflow->withAddedJob(
    //         step: new Job(
    //             TestActionParamFooResponseBar::class,
    //             foo: reference(job: 'not', parameter: 'found')
    //         )
    //     );
    // }

    public function testWithConflictingReferencedParameters(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Incompatible declaration');
        $this->expectExceptionMessage('step2 (argument@foo)');
        $this->expectExceptionMessage('Reference ${step1:missing} not found');
        $this->expectExceptionMessage('not declared by step1');
        workflow(
            step1: job(
                TestActionParamFooResponseBar::class,
                foo: variable('foo')
            ),
            step2: job(
                TestActionParams::class,
                foo: reference(job: 'step1', parameter: 'missing'),
                bar: variable('foo')
            )
        );
    }

    public function testWithConflictingTypeReferencedParameters(): void
    {
        $this->expectException(BadMethodCallException::class);
        workflow(
            step1: job(
                TestActionParamFooResponseBar::class,
                foo: variable('foo')
            ),
            step2: job(
                TestActionObjectConflict::class,
                baz: reference(job: 'step1', parameter: 'bar'),
                bar: variable('foo')
            )
        );
    }
}
