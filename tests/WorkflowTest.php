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
use Chevere\Tests\_resources\src\TestActionParamFooResponseBar;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
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
        $job = job(new TestActionNoParams());
        $jobs = new Jobs(job: $job);
        $workflow = new Workflow($jobs);
        $this->assertCount(0, $workflow->getJobResponseParameters('job'));
        $this->assertCount(1, $workflow);
        $this->assertTrue($workflow->jobs()->has('job'));
        $this->assertSame(['job'], $workflow->jobs()->keys());
    }

    public function testWithAdded(): void
    {
        $job = job(new TestActionNoParams());
        $jobs = new Jobs(job1: $job);
        $workflow = new Workflow($jobs);
        $workflowWithAddedStep = $workflow->withAddedJob(job2: $job);
        $this->assertNotSame($workflow, $workflowWithAddedStep);
        $this->assertCount(2, $workflowWithAddedStep);
        $this->assertTrue($workflowWithAddedStep->jobs()->has('job1'));
        $this->assertTrue($workflowWithAddedStep->jobs()->has('job2'));
        $this->assertSame(['job1', 'job2'], $workflowWithAddedStep->jobs()->keys());
        $this->expectException(OverflowException::class);
        $workflowWithAddedStep->withAddedJob(job1: $job);
    }

    public function testWithAddedJobWithArguments(): void
    {
        $job = job(
            new TestActionParamFooResponseBar(),
            foo: 'bar'
        );
        $workflow = (new Workflow(new Jobs(job: $job)))
            ->withAddedJob(name: $job);
        $this->assertSame($job, $workflow->jobs()->get('name'));
        $this->assertCount(1, $workflow->getJobResponseParameters('job'));
        $workflow->getJobResponseParameters('job')->assertHas('bar');
    }

    public function testWithVariable(): void
    {
        $workflow = new Workflow(
            new Jobs(
                job1:job(
                    new TestActionParamFooResponseBar(),
                    foo: variable('foo')
                ),
            )
        );
        $this->assertTrue($workflow->jobs()->has('job1'));
        $this->assertTrue($workflow->jobs()->variables()->has('foo'));
        $this->assertTrue($workflow->parameters()->has('foo'));
        $workflow = $workflow
            ->withAddedJob(
                job2: job(
                    new TestActionParamFooResponseBar(),
                    foo: variable('foo'),
                )->withRunIf(variable('boolean'))
            );
        $this->assertTrue($workflow->parameters()->has('foo'));
    }

    public function testWithReference(): void
    {
        $workflow = new Workflow(
            new Jobs(
                job1:job(
                    new TestActionParamFooResponseBar(),
                    foo: variable('foo')
                ),
            )
        );
        $this->assertTrue($workflow->jobs()->has('job1'));
        $this->assertTrue($workflow->jobs()->variables()->has('foo'));
        $this->assertTrue($workflow->parameters()->has('foo'));
        $workflow = $workflow
            ->withAddedJob(
                job2:job(
                    new TestActionParamFooResponseBar(),
                    foo: reference('job1', 'bar'),
                )
            );
        $this->assertContains('job1', $workflow->jobs()->get('job2')->dependencies());
        $this->assertTrue($workflow->parameters()->has('foo'));
    }

    public function testWithMissingReference(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Incompatible declaration');
        $this->expectExceptionMessage('job2 (argument@foo)');
        $this->expectExceptionMessage('Reference ${job1:missing} not found');
        $this->expectExceptionMessage('not declared by job1');
        workflow(
            job1: job(
                new TestActionParamFooResponseBar(),
                foo: 'bar'
            ),
            job2: job(
                new TestActionParamFooResponseBar(),
                foo: reference('job1', 'missing'),
            )
        );
    }
}
