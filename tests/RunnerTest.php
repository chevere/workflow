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
use Chevere\Tests\_resources\src\TestActionNoParamsBooleanResponses;
use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Tests\_resources\src\TestActionParamFooResponse1;
use Chevere\Tests\_resources\src\TestActionParamsFooBarResponse2;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use function Chevere\Workflow\async;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use function Chevere\Workflow\reference;
use Chevere\Workflow\Run;
use function Chevere\Workflow\run;
use Chevere\Workflow\Runner;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;
use PHPUnit\Framework\TestCase;

final class RunnerTest extends TestCase
{
    public function testRunnerForArguments(): void
    {
        $container = new Container();
        $jobsRunArguments = [
            'job1' => [
                'foo' => 'viva Chile!',
            ],
            'job2' => [
                'foo' => 'tenemos el litio botado',
                'bar' => 'y no lo sabemos aprovechar',
            ],
            'job3' => [
                'foo' => 'condimento bueno',
            ],
        ];
        $job1 = async(
            TestActionParamFooResponse1::class,
            ...$jobsRunArguments['job1']
        );
        $job2 = async(
            TestActionParamsFooBarResponse2::class,
            ...$jobsRunArguments['job2']
        );
        $job3 = sync(
            TestActionParamFooResponse1::class,
            ...$jobsRunArguments['job3']
        );
        $jobs = [
            'job1' => $job1,
            'job2' => $job2,
            'job3' => $job3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow);
        $runner = new Runner($run, $container);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertExpectedRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow);
        $this->assertExpectedRun($jobs, $jobsRunArguments, $run);
    }

    public function testRunnerForVariables(): void
    {
        $container = new Container();
        $variables = [
            'uno' => 'ha salido un nuevo estilo de baile',
            'dos' => 'y yo, no lo sabia',
            'tres' => 'en las discos todos lo practican',
        ];
        $jobsRunArguments = [
            'job1' => [
                'foo' => $variables['uno'],
            ],
            'job2' => [
                'foo' => $variables['dos'],
                'bar' => $variables['dos'],
            ],
            'job3' => [
                'foo' => $variables['tres'],
            ],
        ];
        $jobsVariables = [
            'job1' => [
                'foo' => variable('uno'),
            ],
            'job2' => [
                'foo' => variable('dos'),
                'bar' => variable('dos'),
            ],
            'job3' => [
                'foo' => variable('tres'),
            ],
        ];
        $job1 = async(
            TestActionParamFooResponse1::class,
            ...$jobsVariables['job1']
        );
        $job2 = async(
            TestActionParamsFooBarResponse2::class,
            ...$jobsVariables['job2']
        );
        $job3 = sync(
            TestActionParamFooResponse1::class,
            ...$jobsVariables['job3']
        );
        $jobs = [
            'job1' => $job1,
            'job2' => $job2,
            'job3' => $job3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow, ...$variables);
        $runner = new Runner($run, $container);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertExpectedRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow, $variables);
        $this->assertExpectedRun($jobs, $jobsRunArguments, $run);
    }

    public function testRunnerForReferences(): void
    {
        $container = new Container();
        $references = [
            'uno' => 'quisiera sacarte a caminar, en un largo tour',
            'dos' => 'por Pudahuel y La bandera',
            'tres' => 'y verías la vida... tal como es',
        ];
        $jobsRunArguments = [
            'job1' => [
                'foo' => $references['uno'],
            ],
            'job2' => [
                'foo' => $references['uno'],
                'bar' => $references['uno'],
            ],
            'job3' => [
                'foo' => $references['uno'] . '^' . $references['uno'],
            ],
        ];
        $job1 = async(
            TestActionParamFooResponse1::class,
            foo: $references['uno'],
        );
        $job2 = async(
            TestActionParamsFooBarResponse2::class,
            foo: reference('job1', 'response1'),
            bar: reference('job1', 'response1'),
        );
        $job3 = sync(
            TestActionParamFooResponse1::class,
            foo: reference('job2', 'response2'),
        );
        $jobs = [
            'job1' => $job1,
            'job2' => $job2,
            'job3' => $job3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow);
        $runner = new Runner($run, $container);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertExpectedRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow);
        $this->assertExpectedRun($jobs, $jobsRunArguments, $run);
    }

    public function testWithRunIfVariable(): void
    {
        $container = new Container();
        $name = 'variable';
        $job = async(TestActionNoParams::class)->withRunIf(variable($name));
        $workflow = workflow(job1: $job);
        $arguments = [
            $name => true,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run, $container);
        $runner = $runner->withRunJob('job1');
        $action = $job->actionName()->__toString();
        $action = new $action();
        $this->assertSame(
            $action->run(),
            $runner->run()->getResponse('job1')->data()
        );
        $arguments = [
            $name => false,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run, $container);
        $runner = $runner->withRunJob('job1');
        $this->assertSame($workflow->jobs()->keys(), $runner->run()->skip()->toArray());
        $run = run($workflow, $arguments);
        $this->assertSame($workflow->jobs()->keys(), $runner->run()->skip()->toArray());
        $this->expectException(OutOfBoundsException::class);
        $runner->run()->getResponse('job1')->data();
    }

    public function testRunIfReference(): void
    {
        $container = new Container();
        $job1 = async(TestActionNoParamsBooleanResponses::class);
        $job2 = async(TestActionNoParamsBooleanResponses::class);
        $job3 = async(TestActionNoParamsIntegerResponse::class);
        $job4 = async(TestActionNoParamsIntegerResponse::class);
        $workflow = workflow(
            job1: $job1,
            job2: $job2->withRunIf(reference('job1', 'true')),
            job3: $job3->withRunIf(reference('job1', 'true')),
            job4: $job4->withDepends('job3')
        );
        $run = new Run($workflow);
        $runner = new Runner($run, $container);
        foreach ($workflow->jobs()->keys() as $name) {
            $runner = $runner->withRunJob($name);
            $runner->run()->getResponse($name);
        }
        $workflow = workflow(
            job1: $job1,
            job2: $job2->withRunIf(reference('job1', 'true'), reference('job1', 'false')),
            job3: $job3->withRunIf(reference('job1', 'true'), reference('job1', 'false')),
            job4: $job1->withDepends('job3')
        );
        $run = new Run($workflow);
        $runner = new Runner($run, $container);
        foreach ($workflow->jobs()->keys() as $name) {
            $runner = $runner->withRunJob($name);
        }
        $jobsKeysSkip = ['job2', 'job3', 'job4'];
        $this->assertSame($jobsKeysSkip, $runner->run()->skip()->toArray());
        $run = run($workflow);
        $this->assertSame($jobsKeysSkip, $runner->run()->skip()->toArray());
    }

    /**
     * @param array<string, JobInterface> $jobs
     */
    private function assertExpectedRun(array $jobs, array $runArguments, RunInterface $run): void
    {
        foreach ($jobs as $name => $job) {
            $actionName = $job->actionName()->__toString();
            $action = new $actionName();
            $this->assertSame(
                $action->run(...$runArguments[$name]),
                $run->getResponse($name)->data()
            );
        }
    }
}
