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

namespace Chevere\Tests\Workflow;

use Chevere\Tests\Workflow\_resources\src\WorkflowTestStep0;
use Chevere\Tests\Workflow\_resources\src\WorkflowTestStep1;
use Chevere\Tests\Workflow\_resources\src\WorkflowTestStep2;
use Chevere\Tests\Workflow\_resources\src\WorkflowTestStep2Conflict;
use Chevere\Tests\Workflow\_resources\src\WorkflowTestStepDeps0;
use Chevere\Tests\Workflow\_resources\src\WorkflowTestStepDeps1;
use Chevere\Throwable\Exceptions\BadMethodCallException;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Step;
use Chevere\Workflow\Steps;
use Chevere\Workflow\Workflow;
use PHPUnit\Framework\TestCase;

final class WorkflowTest extends TestCase
{
    public function testConstructEmpty(): void
    {
        $workflow = new Workflow(new Steps());
        $this->assertCount(0, $workflow);
        $this->expectException(OutOfBoundsException::class);
        $workflow->getVar('not-found');
    }

    public function testConstruct(): void
    {
        $step = new Step(WorkflowTestStep0::class);
        $steps = new Steps(step: $step);
        $workflow = new Workflow($steps);
        $this->assertCount(1, $workflow);
        $this->assertTrue($workflow->steps()->has('step'));
        $this->assertSame(['step'], $workflow->steps()->keys());
    }

    public function testWithAdded(): void
    {
        $step = new Step(WorkflowTestStep0::class);
        $steps = new Steps(step: $step);
        $workflow = new Workflow($steps);
        $workflowWithAddedStep = $workflow->withAddedStep(step2: $step);
        $this->assertNotSame($workflow, $workflowWithAddedStep);
        $this->assertCount(2, $workflowWithAddedStep);
        $this->assertTrue($workflowWithAddedStep->steps()->has('step'));
        $this->assertTrue($workflowWithAddedStep->steps()->has('step2'));
        $this->assertSame(['step', 'step2'], $workflowWithAddedStep->steps()->keys());
        $this->expectException(OverflowException::class);
        $workflowWithAddedStep->withAddedStep(step: $step);
    }

    public function testWithAddedBeforeAndAfter(): void
    {
        $workflow = (new Workflow(new Steps()));
        $workflowWithAddedSteps = $workflow
            ->withAddedStep(step: new Step(WorkflowTestStep0::class))
            ->withAddedStepBefore(
                'step',
                stepBefore: new Step(WorkflowTestStep0::class)
            );
        $this->assertNotSame($workflow, $workflowWithAddedSteps);
        $this->assertSame(['stepBefore', 'step'], $workflowWithAddedSteps->steps()->keys());
        $workflowWithAddedSteps = $workflowWithAddedSteps
            ->withAddedStepAfter(
                'stepBefore',
                stepAfter: new Step(WorkflowTestStep0::class)
            );
        $this->assertNotSame($workflow, $workflowWithAddedSteps);
        $this->assertSame([
            'stepBefore',
            'stepAfter',
            'step',
        ], $workflowWithAddedSteps->steps()->keys());
        $this->expectException(BadMethodCallException::class);
        $workflowWithAddedSteps->withAddedStep(
            step3: new Step(
                WorkflowTestStep1::class,
                missing: '${not-found:reference}'
            )
        );
    }

    public function testWithAddedBeforeOutOfBounds(): void
    {
        $workflow = (new Workflow(new Steps()))
            ->withAddedStep(
                found: new Step(WorkflowTestStep0::class)
            );
        $this->expectException(OutOfBoundsException::class);
        $workflow->withAddedStepBefore(
            'not-found',
            test: new Step(WorkflowTestStep0::class)
        );
    }

    public function testWithAddedAfterOutOfBounds(): void
    {
        $step = new Step(WorkflowTestStep0::class);
        $workflow = (new Workflow(new Steps(step: $step)))
            ->withAddedStep(found: $step);
        $this->expectException(OutOfBoundsException::class);
        $workflow->withAddedStepAfter(
            'not-found',
            test: new Step(WorkflowTestStep0::class)
        );
    }

    public function testWithAddedStepWithArguments(): void
    {
        $step = new Step(
            WorkflowTestStep1::class,
            foo: 'foo'
        );
        $workflow = (new Workflow(new Steps(step: $step)))
            ->withAddedStep(name: $step);
        $this->assertSame($step, $workflow->steps()->get('name'));
    }

    public function testWithReferencedParameters(): void
    {
        $workflow = new Workflow(
            new Steps(
                step1: new Step(
                    WorkflowTestStep1::class,
                    foo: '${foo}'
                )
            )
        );
        $this->assertTrue($workflow->vars()->has('${foo}'));
        $this->assertTrue($workflow->parameters()->has('foo'));
        $this->assertSame(['foo'], $workflow->getVar('${foo}'));
        $workflow = $workflow
            ->withAddedStep(
                step2: new Step(
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
        $workflow->withAddedStep(
            step: new Step(
                WorkflowTestStep1::class,
                foo: '${not:found}'
            )
        );
    }

    public function testConflictingTypeDependentActions(): void
    {
        $workflow = new Workflow(
            new Steps(
                step0: new Step(WorkflowTestStepDeps0::class)
            )
        );
        foreach ((new WorkflowTestStepDeps0())->dependencies()->getIterator() as $key => $className) {
            $this->assertSame($className, $workflow->steps()->dependencies()->key($key));
        }
        $this->expectException(OverflowException::class);
        $workflow->withAddedStep(
            step1: new Step(WorkflowTestStepDeps1::class)
        );
    }

    public function testConflictingParameterType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Workflow(
            new Steps(
                step1: new Step(
                    WorkflowTestStep1::class,
                    foo: '${foo}'
                ),
                step2: new Step(
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
            new Steps(
                step1: new Step(
                    WorkflowTestStep1::class,
                    foo: '${foo}'
                ),
                step2: new Step(
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
            new Steps(
                step1: new Step(
                    WorkflowTestStep1::class,
                    foo: '${foo}'
                ),
                step2: new Step(
                    WorkflowTestStep2Conflict::class,
                    baz: '${step1:bar}',
                    bar: '${foo}'
                )
            )
        );
    }
}
