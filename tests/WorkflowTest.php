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

use Chevere\Tests\src\TestActionAppendString;
use Chevere\Tests\src\TestActionIntToString;
use Chevere\Tests\src\TestActionNoParams;
use Chevere\Tests\src\TestActionNoParamsArrayIntResponse;
use Chevere\Tests\src\TestActionParamFooResponse1;
use Chevere\Tests\src\TestActionParamFooResponseBar;
use Chevere\Tests\src\TestActionVariadic;
use Chevere\Workflow\Jobs;
use Chevere\Workflow\Workflow;
use OverflowException;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\async;
use function Chevere\Workflow\response;
use function Chevere\Workflow\run;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;

final class WorkflowTest extends TestCase
{
    public function testConstruct(): void
    {
        $job = async(new TestActionNoParams());
        $jobs = new Jobs(job: $job);
        $workflow = new Workflow($jobs);
        $this->assertCount(1, $workflow);
        $this->assertTrue($workflow->jobs()->has('job'));
        $this->assertSame(['job'], $workflow->jobs()->keys());
    }

    public function testWithAdded(): void
    {
        $job = async(new TestActionNoParams());
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
        $job = async(
            new TestActionParamFooResponseBar(),
            foo: 'bar'
        );
        $workflow = (new Workflow(new Jobs(job: $job)))
            ->withAddedJob(name: $job);
        $this->assertSame($job, $workflow->jobs()->get('name'));
    }

    public function testWithVariable(): void
    {
        $workflow = new Workflow(
            new Jobs(
                job1: async(
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
                job2: async(
                    new TestActionParamFooResponseBar(),
                    foo: variable('foo'),
                )->withRunIf(variable('boolean'))
            );
        $this->assertTrue($workflow->parameters()->has('foo'));
    }

    public function testWithReference(): void
    {
        $jobs = new Jobs(
            job1: async(
                new TestActionAppendString(),
                string: 'test',
            ),
            job2: async(
                new TestActionAppendString(),
                string: response('job1'),
            )
        );
        $workflow = new Workflow($jobs);
        $this->assertTrue($workflow->jobs()->has('job1'));
        $this->assertContains('job1', $workflow->jobs()->get('job2')->dependencies());
        $run = run($workflow);
        $this->assertSame('test!!', $run->response('job2')->string());
    }

    public function testExpectedRecordsResponseKeyWhenReferenceHasKey(): void
    {
        $jobs = new Jobs(
            job1: async(
                new TestActionParamFooResponse1(),
                foo: 'foo'
            ),
            job2: async(
                new TestActionAppendString(),
                string: response('job1', 'response1')
            ),
            job3: async(
                new TestActionAppendString(),
                string: response('job1')
            )
        );
        $workflow = new Workflow($jobs);
        $this->assertSame(
            ['response1', 'job1'],
            $workflow->referenced()->get('job1')
        );
    }

    public function testVariadicArgumentsAreRecorded(): void
    {
        $workflow = new Workflow(
            new Jobs(
                job2: async(
                    new TestActionNoParamsArrayIntResponse(),
                ),
                job1: async(
                    new TestActionVariadic(),
                    bar1: variable('intVariable'),
                    bar2: response('job2', 'id'),
                ),
            )
        );
        $this->assertTrue($workflow->parameters()->has('intVariable'));
        $this->assertSame(['id'], $workflow->referenced()->get('job2'));
    }

    public function testIntToString(): void
    {
        $workflow = workflow(
            toString: sync(
                new TestActionIntToString(),
                int: 1234
            )
        );
        $run = run($workflow);
        $this->assertSame('1234', $run->response('toString')->string());
    }

    public function testWithAddedJobWithRunIf(): void
    {
        $workflow = new Workflow(
            new Jobs(
                job1: async(new TestActionNoParams())
            )
        );
        $workflow = $workflow->withAddedJob(
            job2: async(
                new TestActionParamFooResponseBar(),
                foo: 'bar'
            )->withRunIf(
                variable('condition')
            )
        );
        $this->assertTrue($workflow->parameters()->has('condition'));
    }
}
