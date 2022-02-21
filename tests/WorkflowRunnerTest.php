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
use function Chevere\Filesystem\fileForPath;
use Chevere\Tests\_resources\src\TestActionWrite;
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
                ...$action1->getArguments(...[
                    'foo' => $foo,
                ])->toArray()
            )->data(),
            $workflowRun->get('step1')->data()
        );
        $foo = $workflowRun->get('step1')->data()['response1'];
        $action2 = new WorkflowRunnerTestStep2();
        $this->assertSame(
            $action2
                ->run(
                    ...$action2->getArguments(...[
                        'foo' => $foo,
                        'bar' => $bar,
                    ])->toArray()
                )
                ->data(),
            $workflowRun->get('step2')->data()
        );
    }

    /**
    * @runInSeparateProcess
    */
    public function testSequentialRunner(): void
    {
        $file = fileForPath(__DIR__ . '/_resources/output-sequential');
        $file->createIfNotExists();
        $file->put('');
        $action = new TestActionWrite();
        $workflow = workflow(
            j1: job(
                TestActionWrite::class,
                file: $file,
            ),
            j2: job(
                TestActionWrite::class,
                file: $file,
            )->withDepends('j1'),
        );
        $arguments = [];
        $workflowRun = new WorkflowRun($workflow, ...$arguments);
        (new WorkflowRunner($workflowRun))
            ->withRun(new Map());
        $this->assertStringEqualsFile(
            $file->path()->__toString(),
            str_repeat($action->flagStart() . $action->flagFinish(), 2)
        );
        $file->removeIfExists();
    }

    /**
    * @runInSeparateProcess
    */
    public function testParallelRunner(): void
    {
        $file = fileForPath(__DIR__ . '/_resources/output-parallel');
        $file->createIfNotExists();
        $file->put('');
        $action = new TestActionWrite();
        $workflow = workflow(
            j1: job(
                TestActionWrite::class,
                file: $file,
            ),
            j2: job(
                TestActionWrite::class,
                file: $file,
            ),
        );
        $arguments = [];
        $run = new WorkflowRun($workflow, ...$arguments);
        $runner = (new WorkflowRunner($run))
            ->withRun(new Map());
        $this->assertStringEqualsFile(
            $file->path()->__toString(),
            str_repeat($action->flagStart(), 2)
            . str_repeat($action->flagFinish(), 2)
        );
        $file->removeIfExists();
    }
}
