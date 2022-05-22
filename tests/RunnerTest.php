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

use Chevere\Container\Container;
use Chevere\Tests\_resources\src\TestActionParamsFooBarResponse2;
use Chevere\Tests\_resources\src\TestActionParamsFooResponse1;
use function Chevere\Workflow\job;
use Chevere\Workflow\Run;
use Chevere\Workflow\Runner;
use function Chevere\Workflow\workflow;
use function Chevere\Workflow\workflowRun;
use function Chevere\Workflow\workflowRunnerForJob;
use PHPUnit\Framework\TestCase;

final class RunnerTest extends TestCase
{
    public function testWorkflowRunner(): void
    {
        $foo = 'hola';
        $bar = 'mundo';
        $workflow = workflow(
            step1: job(
                TestActionParamsFooResponse1::class,
                foo: '${foo}'
            ),
            step2: job(
                TestActionParamsFooBarResponse2::class,
                foo: '${step1:response1}',
                bar: '${bar}'
            )
        );
        $arguments = [
            'foo' => $foo,
            'bar' => $bar,
        ];
        $workflowRun = new Run($workflow, ...$arguments);
        $workflowRunner = new Runner($workflowRun, new Container());
        $workflowRunnerForStep1 = workflowRunnerForJob(
            $workflowRunner,
            'step1'
        );
        $workflowRunner = (new Runner($workflowRun, new Container()))
            ->withRun();
        workflowRunnerForJob($workflowRunner, 'step1');
        $workflowRunnerForStep2 = workflowRunnerForJob(
            $workflowRunner,
            'step2'
        );
        $workflowRun = $workflowRunner->run();
        $this->assertSame(
            $workflowRunnerForStep1->run()->get('step1')->data(),
            $workflowRun->get('step1')->data()
        );
        $this->assertSame(
            $workflowRunnerForStep2->run()->get('step2')->data(),
            $workflowRun->get('step2')->data()
        );
        $this->assertSame($workflowRun, $workflowRunner->run());
        $workflowRunFunction = workflowRun($workflow, $arguments);
        $this->assertEquals(
            $workflowRunFunction->workflow(),
            $workflowRunner->run()->workflow()
        );
        $action1 = new TestActionParamsFooResponse1();
        $this->assertSame(
            $action1->run(foo: $foo),
            $workflowRun->get('step1')->data()
        );
        $foo = $workflowRun->get('step1')->data()['response1'];
        $action2 = new TestActionParamsFooBarResponse2();
        $this->assertSame(
            $action2
                ->run(
                    foo: $foo,
                    bar: $bar,
                ),
            $workflowRun->get('step2')->data()
        );
    }
}
