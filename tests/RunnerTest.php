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
use Chevere\Tests\_resources\src\TestActionParamFooResponse1;
use Chevere\Tests\_resources\src\TestActionParamsFooBarResponse2;
use function Chevere\Workflow\job;
use function Chevere\Workflow\reference;
use Chevere\Workflow\Run;
use function Chevere\Workflow\run;
use Chevere\Workflow\Runner;
use function Chevere\Workflow\runnerForJob;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunnerTest extends TestCase
{
    public function testRunnerForArguments(): void
    {
        $container = new Container();
        $j1Arguments = [
            'foo' => 'viva Chile',
        ];
        $j2Arguments = [
            'foo' => 'hola',
            'bar' => 'mundo',
        ];
        $j1 = job(
            TestActionParamFooResponse1::class,
            ...$j1Arguments
        );
        $j2 = job(
            TestActionParamsFooBarResponse2::class,
            ...$j2Arguments
        );
        $j3 = job(
            TestActionParamFooResponse1::class,
            ...$j1Arguments
        )->withIsSync();
        $workflow = workflow(
            j1: $j1,
            j2: $j2,
            j3: $j3
        );
        $run = new Run($workflow);
        $runner = new Runner($run, $container);
        $runner = $runner->withRun();
        $this->assertSame(
            $j1->getAction()->run(...$j1Arguments),
            $runner->run()->get('j1')->data()
        );
    }

    public function testRunnerForReferences(): void
    {
        $foo = 'hola';
        $bar = 'mundo';
        $workflow = workflow(
            j1: job(
                TestActionParamFooResponse1::class,
                foo: variable('foo')
            ),
            j2: job(
                TestActionParamsFooBarResponse2::class,
                foo: reference(job: 'j1', parameter: 'response1'),
                bar: variable('bar')
            )
        );
        $variables = [
            'foo' => $foo,
            'bar' => $bar,
        ];
        $run = new Run($workflow, ...$variables);
        $runner = new Runner($run, new Container());
        $runnerForJ1 = runnerForJob($runner, 'j1');
        $runner = (new Runner($run, new Container()))
            ->withRun();
        runnerForJob($runner, 'j1');
        $runnerForJ2 = runnerForJob($runner, 'j2');
        $run = $runner->run();
        $this->assertSame(
            $runnerForJ1->run()->get('j1')->data(),
            $run->get('j1')->data()
        );
        $this->assertSame(
            $runnerForJ2->run()->get('j2')->data(),
            $run->get('j2')->data()
        );
        $this->assertSame($run, $runner->run());
        $runFunction = run($workflow, $variables);
        $this->assertEquals(
            $runFunction->workflow(),
            $runner->run()->workflow()
        );
        $action1 = new TestActionParamFooResponse1();
        $this->assertSame(
            $action1->run(foo: $foo),
            $run->get('j1')->data()
        );
        $foo = $run->get('j1')->data()['response1'];
        $action2 = new TestActionParamsFooBarResponse2();
        $this->assertSame(
            $action2
                ->run(
                    foo: $foo,
                    bar: $bar,
                ),
            $run->get('j2')->data()
        );
    }

    public function testWithRunIf(): void
    {
        $container = new Container();
        $name = 'variable';
        $job = job(TestActionNoParams::class)
            ->withRunIf(variable($name));
        $workflow = workflow(j1: $job);
        $arguments = [
            $name => true,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run, $container);
        $runner = $runner->withRun();
        $this->assertSame(
            $job->getAction()->run(),
            $runner->run()->get('j1')->data()
        );
        $arguments = [
            $name => false,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run, $container);
        // Note: RunIf stuff should be skipped, not throwing an exception
        // $this->expectException(RuntimeException::class);
        // $runner->withRun();
    }
}
