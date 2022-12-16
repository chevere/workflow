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
use Chevere\Workflow\Interfaces\RunInterface;
use function Chevere\Workflow\job;
use function Chevere\Workflow\reference;
use Chevere\Workflow\Run;
use function Chevere\Workflow\run;
use Chevere\Workflow\Runner;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunnerTest extends TestCase
{
    public function testRunnerForArguments(): void
    {
        $container = new Container();
        $jobsRunArguments = [
            'j1' => [
                'foo' => 'viva Chile!',
            ],
            'j2' => [
                'foo' => 'tenemos el litio botado',
                'bar' => 'y no lo sabemos aprovechar',
            ],
            'j3' => [
                'foo' => 'condimento bueno',
            ],
        ];
        $j1 = job(
            TestActionParamFooResponse1::class,
            ...$jobsRunArguments['j1']
        );
        $j2 = job(
            TestActionParamsFooBarResponse2::class,
            ...$jobsRunArguments['j2']
        );
        $j3 = job(
            TestActionParamFooResponse1::class,
            ...$jobsRunArguments['j3']
        )->withIsSync();
        $jobs = [
            'j1' => $j1,
            'j2' => $j2,
            'j3' => $j3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow);
        $runner = new Runner($run, $container);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertRunnerAgainstRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow);
        $this->assertRunnerAgainstRun($jobs, $jobsRunArguments, $run);
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
            'j1' => [
                'foo' => $variables['uno'],
            ],
            'j2' => [
                'foo' => $variables['dos'],
                'bar' => $variables['dos'],
            ],
            'j3' => [
                'foo' => $variables['tres'],
            ],
        ];
        $jobsVariables = [
            'j1' => [
                'foo' => variable('uno'),
            ],
            'j2' => [
                'foo' => variable('dos'),
                'bar' => variable('dos'),
            ],
            'j3' => [
                'foo' => variable('tres'),
            ],
        ];
        $j1 = job(
            TestActionParamFooResponse1::class,
            ...$jobsVariables['j1']
        );
        $j2 = job(
            TestActionParamsFooBarResponse2::class,
            ...$jobsVariables['j2']
        );
        $j3 = job(
            TestActionParamFooResponse1::class,
            ...$jobsVariables['j3']
        )->withIsSync();
        $jobs = [
            'j1' => $j1,
            'j2' => $j2,
            'j3' => $j3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow, ...$variables);
        $runner = new Runner($run, $container);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertRunnerAgainstRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow, $variables);
        $this->assertRunnerAgainstRun($jobs, $jobsRunArguments, $run);
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
            'j1' => [
                'foo' => $references['uno'],
            ],
            'j2' => [
                'foo' => $references['uno'],
                'bar' => $references['uno'],
            ],
            'j3' => [
                'foo' => $references['uno'] . '^' . $references['uno'],
            ],
        ];
        $jobsReferences = [
            'j1' => [
                'foo' => $references['uno'],
            ],
            'j2' => [
                'foo' => reference('j1', 'response1'),
                'bar' => reference('j1', 'response1'),
            ],
            'j3' => [
                'foo' => reference('j2', 'response2'),
            ],
        ];
        $j1 = job(
            TestActionParamFooResponse1::class,
            ...$jobsReferences['j1']
        );
        $j2 = job(
            TestActionParamsFooBarResponse2::class,
            ...$jobsReferences['j2']
        );
        $j3 = job(
            TestActionParamFooResponse1::class,
            ...$jobsReferences['j3']
        )->withIsSync();
        $jobs = [
            'j1' => $j1,
            'j2' => $j2,
            'j3' => $j3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow, ...$references);
        $runner = new Runner($run, $container);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertRunnerAgainstRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow, $references);
        $this->assertRunnerAgainstRun($jobs, $jobsRunArguments, $run);
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

    private function assertRunnerAgainstRun(array $jobs, array $runArguments, RunInterface $run): void
    {
        foreach ($jobs as $name => $job) {
            $this->assertSame(
                $job->getAction()->run(...$runArguments[$name]),
                $run->get($name)->data()
            );
        }
    }
}
