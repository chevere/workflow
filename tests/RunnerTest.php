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
use Chevere\Tests\_resources\src\TestActionNoParams;
use Chevere\Tests\_resources\src\TestActionParamsFooBarResponse2;
use Chevere\Tests\_resources\src\TestActionParamsFooResponse1;
use function Chevere\Workflow\job;
use function Chevere\Workflow\reference;
use Chevere\Workflow\Run;
use function Chevere\Workflow\run;
use Chevere\Workflow\Runner;
use function Chevere\Workflow\runnerForJob;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;
use PHPUnit\Framework\TestCase;

final class RunnerTest extends TestCase
{
    public function testRunner(): void
    {
        $foo = 'hola';
        $bar = 'mundo';
        $workflow = workflow(
            step1: job(
                TestActionParamsFooResponse1::class,
                foo: variable('foo')
            ),
            step2: job(
                TestActionParamsFooBarResponse2::class,
                foo: reference(job: 'step1', parameter: 'response1'),
                bar: variable('bar')
            )
        );
        $arguments = [
            'foo' => $foo,
            'bar' => $bar,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run, new Container());
        $runnerForStep1 = runnerForJob(
            $runner,
            'step1'
        );
        $runner = (new Runner($run, new Container()))
            ->withRun();
        runnerForJob($runner, 'step1');
        $workflowRunnerForStep2 = runnerForJob(
            $runner,
            'step2'
        );
        $run = $runner->run();
        $this->assertSame(
            $runnerForStep1->run()->get('step1')->data(),
            $run->get('step1')->data()
        );
        $this->assertSame(
            $workflowRunnerForStep2->run()->get('step2')->data(),
            $run->get('step2')->data()
        );
        $this->assertSame($run, $runner->run());
        $runFunction = run($workflow, $arguments);
        $this->assertEquals(
            $runFunction->workflow(),
            $runner->run()->workflow()
        );
        $action1 = new TestActionParamsFooResponse1();
        $this->assertSame(
            $action1->run(foo: $foo),
            $run->get('step1')->data()
        );
        $foo = $run->get('step1')->data()['response1'];
        $action2 = new TestActionParamsFooBarResponse2();
        $this->assertSame(
            $action2
                ->run(
                    foo: $foo,
                    bar: $bar,
                ),
            $run->get('step2')->data()
        );
    }

    public function testWithRunIf(): void
    {
        $name = 'variable';
        $job = job(TestActionNoParams::class)
            ->withRunIf(variable($name));
        $workflow = workflow(j1: $job);
        $this->assertCount(1, $workflow->parameters());
        $arguments = [
            $name => true,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run, new Container());
    }
}
