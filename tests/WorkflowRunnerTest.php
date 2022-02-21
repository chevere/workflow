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

use Chevere\DataStructure\Map;
use Chevere\Tests\_resources\src\ActionTestAction;
use Chevere\Tests\_resources\src\WorkflowRunnerTestStep1;
use Chevere\Tests\_resources\src\WorkflowRunnerTestStep2;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use function Chevere\Workflow\workflow;
use Chevere\Workflow\Workflow;
use Chevere\Workflow\WorkflowRun;
use Chevere\Workflow\WorkflowRunner;
use PHPUnit\Framework\TestCase;

final class WorkflowRunnerTest extends TestCase
{
    public function testWorkflowRunner(): void
    {
        $foo = 'hola';
        $bar = 'mundo';
        $workflow = (new Workflow(new Jobs()))
            ->withAddedJob(
                step1: job(
                    WorkflowRunnerTestStep1::class,
                    foo: '${foo}'
                ),
                step2: job(
                    WorkflowRunnerTestStep2::class,
                    foo: '${step1:response1}',
                    bar: '${bar}'
                )->withDepends('step1')
            );
        $arguments = [
            'foo' => $foo,
            'bar' => $bar,
        ];
        $workflowRun = new WorkflowRun($workflow, ...$arguments);
        $container = new Map();
        $workflowRunner = (new WorkflowRunner($workflowRun))
            ->withRun($container);
        $workflowRun = $workflowRunner->workflowRun();
        $this->assertSame($workflowRun, $workflowRunner->workflowRun());
        $action1 = new WorkflowRunnerTestStep1();
        $this->assertSame(
            $action1->run(
                $action1->getArguments(...[
                    'foo' => $foo,
                ])
            )->data(),
            $workflowRun->get('step1')->data()
        );
        $foo = $workflowRun->get('step1')->data()['response1'];
        $action2 = new WorkflowRunnerTestStep2();
        $this->assertSame(
            $action2
                ->run(
                    $action2->getArguments(...[
                        'foo' => $foo,
                        'bar' => $bar,
                    ])
                )
                ->data(),
            $workflowRun->get('step2')->data()
        );
    }

    public function testParallelRunner(): void
    {
        $workflow = workflow(
            j1: job(
                ActionTestAction::class,
            ),
            j2: job(
                ActionTestAction::class,
            ),
        );
        $arguments = [];
        $workflowRun = new WorkflowRun($workflow, ...$arguments);
        $container = new Map();
        $workflowRunner = (new WorkflowRunner($workflowRun))
            ->withRun($container);
        $workflowRun = $workflowRunner->workflowRun();
        $this->assertSame($workflowRun, $workflowRunner->workflowRun());
    }
}
