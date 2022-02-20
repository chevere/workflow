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

use Chevere\Tests\_resources\src\WorkflowTestStep0;
use Chevere\Tests\_resources\src\WorkflowTestStep1;
use Chevere\Tests\_resources\src\WorkflowTestStep2;
use Chevere\Tests\_resources\src\WorkflowTestStep2Conflict;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Job;
use Chevere\Workflow\Jobs;
use Chevere\Workflow\Workflow;
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase
{
    public function testConstructEmpty(): void
    {
        $workflow = new Workflow(new Jobs());
        $this->assertCount(0, $workflow);
        $this->expectException(OutOfBoundsException::class);
        $workflow->getVar('not-found');
    }

    public function testConstruct(): void
    {
        $step = new Job(WorkflowTestStep0::class);
        $steps = new Jobs(step: $step);
        $workflow = new Workflow($steps);
        $this->assertCount(1, $workflow);
        $this->assertTrue($workflow->jobs()->has('step'));
        $this->assertSame(['step'], $workflow->jobs()->keys());
    }

    public function testWithAdded(): void
    {
        $step = new Job(WorkflowTestStep0::class);
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
            WorkflowTestStep1::class,
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
                    WorkflowTestStep1::class,
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
                    WorkflowTestStep2::class,
                    foo: '${step1:bar}',
                    bar: '${foo}'
                )
            );
        $this->assertTrue($workflow->vars()->has('${foo}'));
        $this->assertTrue($workflow->vars()->has('${step1:bar}'));
        $this->assertTrue($workflow->parameters()->has('foo'));
        $this->assertSame(['foo'], $workflow->getVar('${foo}'));
        $this->assertSame(['step1', 'bar'], $workflow->getVar('${step1:bar}'));
        $this->expectException(InvalidArgumentException::class);
        $workflow->withAddedJob(
            step: new Job(
                WorkflowTestStep1::class,
                foo: '${not:found}'
            )
        );
    }

    public function testConflictingParameterType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Workflow(
            new Jobs(
                step1: new Job(
                    WorkflowTestStep1::class,
                    foo: '${foo}'
                ),
                step2: new Job(
                    WorkflowTestStep2Conflict::class,
                    baz: '${foo}',
                    bar: 'test'
                )
            )
        );
    }

    public function testWithConflictingReferencedParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Workflow(
            new Jobs(
                step1: new Job(
                    WorkflowTestStep1::class,
                    foo: '${foo}'
                ),
                step2: new Job(
                    WorkflowTestStep2::class,
                    foo: '${step1:missing}',
                    bar: '${foo}'
                )
            )
        );
    }

    public function testWithConflictingTypeReferencedParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Workflow(
            new Jobs(
                step1: new Job(
                    WorkflowTestStep1::class,
                    foo: '${foo}'
                ),
                step2: new Job(
                    WorkflowTestStep2Conflict::class,
                    baz: '${step1:bar}',
                    bar: '${foo}'
                )
            )
        );
    }
}
