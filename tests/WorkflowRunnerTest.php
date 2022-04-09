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
use Chevere\Tests\_resources\src\WorkflowRunnerTestJob1;
use Chevere\Tests\_resources\src\WorkflowRunnerTestJob2;
use function Chevere\Workflow\job;
use function Chevere\Workflow\workflow;
use function Chevere\Workflow\workflowRun;
use Chevere\Workflow\WorkflowRun;
use Chevere\Workflow\WorkflowRunner;
use PHPUnit\Framework\TestCase;

final class WorkflowRunnerTest extends TestCase
{
    public function testWorkflowRunner(): void
    {
        $foo = 'hola';
        $bar = 'mundo';
        $workflow = workflow(
            step1: job(
                WorkflowRunnerTestJob1::class,
                foo: '${foo}'
            ),
            step2: job(
                WorkflowRunnerTestJob2::class,
                foo: '${step1:response1}',
                bar: '${bar}'
            )->withDepends('step1')
        );
        $arguments = [
            'foo' => $foo,
            'bar' => $bar,
        ];
        $workflowRun = new WorkflowRun($workflow, ...$arguments);
        $workflowRunner = (new WorkflowRunner($workflowRun, new Container()))
            ->withRun();
        $workflowRun = $workflowRunner->workflowRun();
        $this->assertSame($workflowRun, $workflowRunner->workflowRun());
        $workflowRunFunction = workflowRun($workflow, $arguments);
        $this->assertEquals($workflowRunFunction->workflow(), $workflowRunner->workflowRun()->workflow());
        $action1 = new WorkflowRunnerTestJob1();
        $this->assertSame(
            $action1->run(
                ...$action1->getArguments(...[
                    'foo' => $foo,
                ])->toArray()
            ),
            $workflowRun->get('step1')->data()
        );
        $foo = $workflowRun->get('step1')->data()['response1'];
        $action2 = new WorkflowRunnerTestJob2();
        $this->assertSame(
            $action2
                ->run(
                    ...$action2->getArguments(...[
                        'foo' => $foo,
                        'bar' => $bar,
                    ])->toArray()
                ),
            $workflowRun->get('step2')->data()
        );
    }
}
