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
use function Chevere\DataStructure\vectorToArray;
use Chevere\Tests\_resources\src\TestActionNoParams;
use Chevere\Tests\_resources\src\TestActionNoParamsBooleanResponses;
use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Tests\_resources\src\TestActionParamFooResponse1;
use Chevere\Tests\_resources\src\TestActionParamsFooBarResponse2;
use Chevere\Throwable\Exceptions\OutOfRangeException;
use Chevere\Workflow\Interfaces\RunInterface;
use function Chevere\Workflow\job;
use function Chevere\Workflow\reference;
use Chevere\Workflow\Run;
use function Chevere\Workflow\run;
use Chevere\Workflow\Runner;
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
        $job1 = job(
            TestActionParamFooResponse1::class,
            ...$jobsRunArguments['job1']
        );
        $job2 = job(
            TestActionParamsFooBarResponse2::class,
            ...$jobsRunArguments['job2']
        );
        $job3 = job(
            TestActionParamFooResponse1::class,
            ...$jobsRunArguments['job3']
        )->withIsSync();
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
        $job1 = job(
            TestActionParamFooResponse1::class,
            ...$jobsVariables['job1']
        );
        $job2 = job(
            TestActionParamsFooBarResponse2::class,
            ...$jobsVariables['job2']
        );
        $job3 = job(
            TestActionParamFooResponse1::class,
            ...$jobsVariables['job3']
        )->withIsSync();
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
        $jobsReferences = [
            'job1' => [
                'foo' => $references['uno'],
            ],
            'job2' => [
                'foo' => reference('job1', 'response1'),
                'bar' => reference('job1', 'response1'),
            ],
            'job3' => [
                'foo' => reference('job2', 'response2'),
            ],
        ];
        $job1 = job(
            TestActionParamFooResponse1::class,
            ...$jobsReferences['job1']
        );
        $job2 = job(
            TestActionParamsFooBarResponse2::class,
            ...$jobsReferences['job2']
        );
        $job3 = job(
            TestActionParamFooResponse1::class,
            ...$jobsReferences['job3']
        )->withIsSync();
        $jobs = [
            'job1' => $job1,
            'job2' => $job2,
            'job3' => $job3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow, ...$references);
        $runner = new Runner($run, $container);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertExpectedRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow, $references);
        $this->assertExpectedRun($jobs, $jobsRunArguments, $run);
    }

    public function testWithRunIfVariable(): void
    {
        $container = new Container();
        $name = 'variable';
        $job = job(TestActionNoParams::class)->withRunIf(variable($name));
        $workflow = workflow(job1: $job);
        $arguments = [
            $name => true,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run, $container);
        $runner = $runner->withRunJob('job1');
        $this->assertSame(
            $job->getAction()->run(),
            $runner->run()->getResponse('job1')->data()
        );
        $arguments = [
            $name => false,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run, $container);
        $runner = $runner->withRunJob('job1');
        $this->assertSame(['job1'], vectorToArray($runner->run()->skip()));
        $run = run($workflow, $arguments);
        $this->assertSame(['job1'], vectorToArray($runner->run()->skip()));
        $this->expectException(OutOfRangeException::class);
        $runner->run()->getResponse('job1')->data();
    }

    public function testRunIfReference(): void
    {
        $container = new Container();
        $job1 = job(TestActionNoParamsBooleanResponses::class);
        $job2 = job(TestActionNoParamsBooleanResponses::class);
        $job3 = job(TestActionNoParamsIntegerResponse::class);
        $workflow = workflow(
            job1: $job1,
            job2: $job2->withRunIf(reference('job1', 'true')),
            job3: $job3->withRunIf(reference('job1', 'true')),
        );
        $run = new Run($workflow);
        $runner = new Runner($run, $container);
        $runner = $runner
            ->withRunJob('job1')
            ->withRunJob('job2')
            ->withRunJob('job3');
        $runner->run()->getResponse('job2');
        $runner->run()->getResponse('job3');
        $workflow = workflow(
            job1: $job1,
            job2: $job2->withRunIf(reference('job1', 'true'), reference('job1', 'false')),
            job3: $job3->withRunIf(reference('job1', 'true'), reference('job1', 'false')),
            job4: $job1->withDepends('job3')
        );
        $run = new Run($workflow);
        $runner = new Runner($run, $container);
        $runner = $runner
            ->withRunJob('job1')
            ->withRunJob('job2')
            ->withRunJob('job3')
            ->withRunJob('job4');
        $this->assertSame(['job2', 'job3'], vectorToArray($runner->run()->skip()));
        $run = run($workflow);
        $this->assertSame(['job2', 'job3'], vectorToArray($runner->run()->skip()));
    }

    private function assertExpectedRun(array $jobs, array $runArguments, RunInterface $run): void
    {
        foreach ($jobs as $name => $job) {
            $this->assertSame(
                $job->getAction()->run(...$runArguments[$name]),
                $run->getResponse($name)->data()
            );
        }
    }
}
