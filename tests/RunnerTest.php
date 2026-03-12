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

use Chevere\Action\Action;
use Chevere\Action\Exceptions\ActionException;
use Chevere\Container\Container;
use Chevere\Container\Exceptions\ContainerException;
use Chevere\Parameter\Attributes\_bool;
use Chevere\Parameter\Attributes\_float;
use Chevere\Parameter\Attributes\_int;
use Chevere\Parameter\Attributes\_return;
use Chevere\Parameter\Attributes\_union;
use Chevere\Tests\src\TestActionDelays;
use Chevere\Tests\src\TestActionDependsNestedNoParams;
use Chevere\Tests\src\TestActionDependsNoParams;
use Chevere\Tests\src\TestActionIntToString;
use Chevere\Tests\src\TestActionInvalidAssert;
use Chevere\Tests\src\TestActionNoParams;
use Chevere\Tests\src\TestActionNoParamsArrayIntResponse;
use Chevere\Tests\src\TestActionNoParamsBoolResponses;
use Chevere\Tests\src\TestActionParamFooResponse1;
use Chevere\Tests\src\TestActionParamsFooBarResponse2;
use Chevere\Tests\src\TestActionThrows;
use Chevere\Tests\src\TestActionUnion;
use Chevere\Tests\src\TestActionVariadic;
use Chevere\Tests\src\TestActionWorksOnNAttempt;
use Chevere\Tests\src\TestEntity;
use Chevere\Workflow\Exceptions\RunnerException;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Run;
use Chevere\Workflow\Runner;
use Chevere\Workflow\Traits\ExpectWorkflowExceptionTrait;
use Exception;
use LogicException;
use OutOfBoundsException;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use function Chevere\Workflow\async;
use function Chevere\Workflow\response;
use function Chevere\Workflow\run;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;

final class RunnerTest extends TestCase
{
    use ExpectWorkflowExceptionTrait;

    public function testWithRun(): void
    {
        $workflow = workflow();
        $run = new Run($workflow);
        $runner = new Runner($run);
        $with = $runner->withRun();
        $this->assertEquals($runner, $with);
        $this->assertNotSame($runner, $with);
    }

    public function testWithRunJob(): void
    {
        $action = new TestActionNoParams();
        $workflow = workflow(job1: async($action));
        $run = new Run($workflow);
        $runner = new Runner($run);
        $runnerWith = $runner->withRunJob('job1');
        $this->assertNotSame($runner, $runnerWith);
    }

    public function testRunnerForArguments(): void
    {
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
            new TestActionParamFooResponse1(),
            ...$jobsRunArguments['job1']
        );
        $job2 = async(
            new TestActionParamsFooBarResponse2(),
            ...$jobsRunArguments['job2']
        );
        $job3 = sync(
            new TestActionParamFooResponse1(),
            ...$jobsRunArguments['job3']
        );
        $jobs = [
            'job1' => $job1,
            'job2' => $job2,
            'job3' => $job3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow);
        $runner = new Runner($run);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertExpectedRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow);
        $this->assertExpectedRun($jobs, $jobsRunArguments, $run);
    }

    public function testRunnerForVariables(): void
    {
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
            new TestActionParamFooResponse1(),
            ...$jobsVariables['job1']
        );
        $job2 = async(
            new TestActionParamsFooBarResponse2(),
            ...$jobsVariables['job2']
        );
        $job3 = sync(
            new TestActionParamFooResponse1(),
            ...$jobsVariables['job3']
        );
        $jobs = [
            'job1' => $job1,
            'job2' => $job2,
            'job3' => $job3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow, ...$variables);
        $runner = new Runner($run);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertExpectedRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow, ...$variables);
        $this->assertExpectedRun($jobs, $jobsRunArguments, $run);
    }

    public function testRunnerForReferences(): void
    {
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
            new TestActionParamFooResponse1(),
            foo: $references['uno'],
        );
        $job2 = async(
            new TestActionParamsFooBarResponse2(),
            foo: response('job1', 'response1'),
            bar: response('job1', 'response1'),
        );
        $job3 = sync(
            new TestActionParamFooResponse1(),
            foo: response('job2', 'response2'),
        );
        $jobs = [
            'job1' => $job1,
            'job2' => $job2,
            'job3' => $job3,
        ];
        $workflow = workflow(...$jobs);
        $run = new Run($workflow);
        $runner = new Runner($run);
        foreach (array_keys($jobs) as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertExpectedRun($jobs, $jobsRunArguments, $runner->run());
        $run = run($workflow);
        $this->assertExpectedRun($jobs, $jobsRunArguments, $run);
    }

    public function testWithRunIfVariable(): void
    {
        $name = 'variable';
        $job = async(new TestActionNoParams())
            ->withRunIf(variable($name));
        $workflow = workflow(job1: $job);
        $arguments = [
            $name => true,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run);
        $runner = $runner->withRunJob('job1');
        $action = $job->action();
        $this->assertSame(
            $action->__invoke(),
            $runner->run()->response('job1')->array()
        );
        $arguments = [
            $name => false,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run);
        $runner = $runner->withRunJob('job1');
        $this->assertSame($workflow->jobs()->keys(), $runner->run()->skip()->toArray());
        $run = run($workflow, ...$arguments);
        $this->assertSame($workflow->jobs()->keys(), $runner->run()->skip()->toArray());
        $this->expectException(OutOfBoundsException::class);
        $runner->run()->response('job1');
    }

    public function testWithRunIfNotVariable(): void
    {
        $name = 'variable';
        $job = async(new TestActionNoParams())
            ->withRunIfNot(variable($name));
        $workflow = workflow(job1: $job);
        $arguments = [
            $name => true,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run);
        $runner = $runner->withRunJob('job1');
        $this->assertSame($workflow->jobs()->keys(), $runner->run()->skip()->toArray());
        $arguments = [
            $name => false,
        ];
        $run = new Run($workflow, ...$arguments);
        $runner = new Runner($run);
        $runner = $runner->withRunJob('job1');
        $action = $job->action();
        $this->assertSame(
            $action->__invoke(),
            $runner->run()->response('job1')->array()
        );
        $run = run($workflow, ...$arguments);
        $this->assertSame(
            $action->__invoke(),
            $run->response('job1')->array()
        );
    }

    public function testRunIfReference(): void
    {
        $job1 = async(new TestActionNoParamsBoolResponses());
        $job2 = async(new TestActionNoParamsBoolResponses());
        $job3 = async(new TestActionNoParamsArrayIntResponse());
        $job4 = async(new TestActionNoParamsArrayIntResponse());
        $workflow = workflow(
            job1: $job1,
            job2: $job2->withRunIf(response('job1', 'true')),
            job3: $job3->withRunIf(response('job1', 'true')),
            job4: $job4->withDepends('job3')
        );
        $run = new Run($workflow);
        $runner = new Runner($run);
        foreach ($workflow->jobs()->keys() as $name) {
            $runner = $runner->withRunJob($name);
            $runner->run()->response($name)->array();
        }
        $workflow = workflow(
            job1: $job1,
            job2: $job2->withRunIf(response('job1', 'true'), response('job1', 'false')),
            job3: $job3->withRunIf(response('job1', 'true'), response('job1', 'false')),
            job4: $job1->withDepends('job3')
        );
        $run = new Run($workflow);
        $runner = new Runner($run);
        foreach ($workflow->jobs()->keys() as $name) {
            $runner = $runner->withRunJob($name);
        }
        $jobsKeysSkip = ['job2', 'job3', 'job4'];
        $this->assertSame($jobsKeysSkip, $runner->run()->skip()->toArray());
        $run = run($workflow);
        $this->assertSame($jobsKeysSkip, $runner->run()->skip()->toArray());
    }

    public function testRunIfNotReference(): void
    {
        $job1 = async(new TestActionNoParamsBoolResponses());
        $job2 = async(new TestActionNoParamsBoolResponses());
        $job3 = async(new TestActionNoParamsArrayIntResponse());
        $job4 = async(new TestActionNoParamsArrayIntResponse());
        $workflow = workflow(
            job1: $job1,
            job2: $job2->withRunIfNot(response('job1', 'true')),
            job3: $job3->withRunIfNot(response('job1', 'true')),
            job4: $job4->withDepends('job3')
        );
        $run = new Run($workflow);
        $runner = new Runner($run);
        foreach ($workflow->jobs()->keys() as $name) {
            $runner = $runner->withRunJob($name);
        }
        $jobsKeysSkip = ['job2', 'job3', 'job4'];
        $this->assertSame($jobsKeysSkip, $runner->run()->skip()->toArray());
        $run = run($workflow);
        $this->assertSame($jobsKeysSkip, $run->skip()->toArray());
    }

    public function testRunIfReferenceNoKey(): void
    {
        $job1 = sync(#[_return(new _bool())] function (): bool {
            return true;
        });
        $job2 = async(new TestActionNoParams())->withRunIf(response('job1'));
        $workflow = workflow(
            job1: $job1,
            job2: $job2
        );
        $run = new Run($workflow);
        $runner = new Runner($run);
        foreach ($workflow->jobs()->keys() as $name) {
            $runner = $runner->withRunJob($name);
        }
        $this->assertFalse($runner->run()->skip()->contains('job2'));
        $run = run($workflow);
        $this->assertFalse($run->skip()->contains('job2'));
        $job1False = sync(#[_return(new _bool())] function (): bool {
            return false;
        });
        $workflow2 = workflow(job1: $job1False, job2: $job2);
        $run2 = run($workflow2);
        $this->assertTrue($run2->skip()->contains('job2'));
    }

    #[DataProvider('dataProviderRunIf')]
    public function testRunIf(mixed $runIf): void
    {
        $closure = fn () => $runIf;
        $job = async(new TestActionNoParams())
            ->withRunIf($closure);
        $workflow = workflow(job1: $job);
        $run = run($workflow);
        $this->assertSame(
            ! $runIf,
            $run->skip()->contains('job1'),
        );
    }

    #[DataProvider('dataProviderRunIf')]
    public function testRunIfNot(mixed $runIf): void
    {
        $closure = fn () => $runIf;
        $job = async(new TestActionNoParams())
            ->withRunIfNot($closure);
        $workflow = workflow(job1: $job);
        $run = run($workflow);
        $this->assertSame(
            (bool) $runIf,
            $run->skip()->contains('job1'),
        );
    }

    public static function dataProviderRunIf(): array
    {
        return [
            [true],
            [false],
            [1],
            [0],
            [200],
        ];
    }

    public function testRunIfNotCallableOverflow(): void
    {
        $this->expectException(OverflowException::class);
        $closure = fn () => true;
        async(new TestActionNoParams())
            ->withRunIfNot($closure, $closure);
    }

    public function testRunIfCallableOverflow(): void
    {
        $this->expectException(OverflowException::class);
        $callable = fn () => true;
        async(new TestActionNoParams())
            ->withRunIf($callable, $callable);
    }

    public function testActionThrows(): void
    {
        $closure = fn () => run(
            workflow(
                job1: sync(
                    new TestActionThrows()
                ),
            )
        );
        $this->expectWorkflowException(
            closure: $closure,
            instance: Exception::class,
            job: 'job1',
            message: 'Test exception',
            code: 666
        );
    }

    public function testActionUnion(): void
    {
        $run = run(
            workflow(
                job1: sync(
                    new TestActionNoParamsArrayIntResponse(),
                ),
                job2: sync(
                    new TestActionUnion(),
                    foo: response('job1', 'id')
                ),
            ),
        );
        $this->assertSame(
            [
                'foo' => $run->response('job1', 'id')->int(),
            ],
            $run->response('job2')->array()
        );
    }

    public function testActionUnionMultipleAlternatives(): void
    {
        $run = run(
            workflow(
                job1: sync(
                    #[_return(new _union(new _int(), new _float()))]
                    function (): int|float {
                        return 123;
                    }
                ),
                job2: sync(
                    new class() extends Action {
                        public function __invoke(float|int $foo): array
                        {
                            return [
                                'foo' => $foo,
                            ];
                        }
                    },
                    foo: response('job1')
                ),
            ),
        );
        $this->assertSame(
            [
                'foo' => $run->response('job1')->int(),
            ],
            $run->response('job2')->array()
        );
    }

    public function testActionVariadic(): void
    {
        $run = run(
            workflow(
                job1: sync(
                    new TestActionIntToString(),
                    int: variable('intVariable'),
                ),
                job2: sync(
                    new TestActionNoParamsArrayIntResponse(),
                ),
                job3: sync(
                    new TestActionVariadic(),
                    bar1: variable('intVariable'),
                    bar2: response('job2', 'id'),
                ),
            ),
            intVariable: 321
        );
        $this->assertSame(
            '321',
            $run->response('job1')->string()
        );
        $this->assertSame(
            [
                'id' => 123,
            ],
            $run->response('job2')->array()
        );
        $this->assertSame(
            [
                'foo' => 'baz',
                'bar' => [
                    'bar1' => 321,
                    'bar2' => 123,
                ],
            ],
            $run->response('job3')->array()
        );
    }

    public function testActionVariadicPositional(): void
    {
        $run = run(
            workflow(
                job1: sync(
                    new TestActionIntToString(),
                    int: variable('intVariable'),
                ),
                job2: sync(
                    new TestActionNoParamsArrayIntResponse(),
                ),
                job3: sync(
                    new TestActionVariadic(),
                    'custom',
                    variable('intVariable'),
                    response('job2', 'id'),
                ),
            ),
            intVariable: 321
        );

        $this->assertSame(
            [
                'foo' => 'custom',
                'bar' => [
                    321,
                    123,
                ],
            ],
            $run->response('job3')->array()
        );
    }

    public function testActionVariadicNamedFooIsPreserved(): void
    {
        $run = run(
            workflow(
                job1: sync(
                    new TestActionIntToString(),
                    int: variable('intVariable'),
                ),
                job2: sync(
                    new TestActionNoParamsArrayIntResponse(),
                ),
                job3: sync(
                    new TestActionVariadic(),
                    foo: 'custom',
                    bar1: variable('intVariable'),
                    bar2: response('job2', 'id'),
                ),
            ),
            intVariable: 321
        );

        $this->assertSame(
            [
                'foo' => 'custom',
                'bar' => [
                    'bar1' => 321,
                    'bar2' => 123,
                ],
            ],
            $run->response('job3')->array()
        );
    }

    public function testRunnerActionClassNameWithDependency(): void
    {
        $this->expectNotToPerformAssertions();
        $workflow = workflow(
            job1: sync(TestActionDependsNoParams::class),
        );
        run($workflow);
        $container = new Container(dependency: new stdClass());
        run($workflow, $container);
    }

    public function testRunnerActionClassNameMissingDependency(): void
    {
        $workflow = workflow(
            job1: sync(TestActionDependsNestedNoParams::class),
        );
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [nestedDependency]: Failed to resolve dependencies for `Chevere\Tests\src\TestActionDependsNoParams`: Missing required argument(s): `dependency`
            PLAIN
        );
        run($workflow);
    }

    public function testJobWithRetryFailure(): void
    {
        $workflow = workflow(
            job1: sync(new TestActionWorksOnNAttempt(5))
                ->withRetry(
                    maxAttempts: 4
                ),
        );
        $this->expectException(RunnerException::class);
        $this->expectExceptionMessage(
            <<<PLAIN
            [job1]: [4/4] Attempt 4 failed, required attempt 5
            PLAIN
        );
        run($workflow);
    }

    public function testJobWithRetrySuccess(): void
    {
        $workflow = workflow(
            job1: sync(new TestActionWorksOnNAttempt(5))
                ->withRetry(
                    maxAttempts: 5
                ),
        );
        $third = run($workflow);
        $this->assertSame([
            'attempt' => 5,
        ], $third->response('job1')->array());
    }

    public function testJobWithRetryTimeout(): void
    {
        $workflow = workflow(
            job1: sync(new TestActionDelays(), seconds: 2.0)
                ->withRetry(
                    timeout: 1,
                    maxAttempts: 3
                ),
        );
        $this->expectException(RunnerException::class);
        $this->expectExceptionMessage('[job1]: [1/3] The operation was cancelled');
        run($workflow);
    }

    public function testJobWithRetryDelay(): void
    {
        $delaySeconds = 1;
        $workflow = workflow(
            job1: sync(new TestActionWorksOnNAttempt(2))
                ->withRetry(
                    maxAttempts: 2,
                    delay: $delaySeconds
                ),
        );
        $startTime = microtime(true);
        $run = run($workflow);
        $endTime = microtime(true);
        $elapsed = $endTime - $startTime;
        // 1 retry with 1s delay = 1s minimum
        $expectedMinDelay = $delaySeconds;
        $this->assertGreaterThanOrEqual($expectedMinDelay, $elapsed);
        $this->assertLessThan($expectedMinDelay + 0.5, $elapsed);
        $this->assertSame([
            'attempt' => 2,
        ], $run->response('job1')->array());
    }

    public function testJobsDependsOnAsyncRetriedJobWithTimeout(): void
    {
        $this->expectNotToPerformAssertions();
        $workflow = workflow(
            job1: async(new TestActionWorksOnNAttempt(2))
                ->withRetry(
                    timeout: 100,
                    maxAttempts: 2,
                ),
            job2: async(new TestActionWorksOnNAttempt(3))
                ->withRetry(
                    timeout: 100,
                    maxAttempts: 3,
                ),
            job3: sync(
                function (int $res1, int $res2) {
                    if ($res1 !== 2) {
                        throw new LogicException("Expected attempt 2, got {$res1}");
                    }
                    if ($res2 !== 3) {
                        throw new LogicException("Expected attempt 3, got {$res2}");
                    }
                },
                res1: response('job1', 'attempt'),
                res2: response('job2', 'attempt'),
            )
        );
        run($workflow);
    }

    public function testWithRunJobAssertsAction(): void
    {
        $workflow = workflow(job1: async(TestActionInvalidAssert::class));
        $run = new Run($workflow);
        $runner = new Runner($run);
        $this->expectException(ActionException::class);
        $runner->withRunJob('job1');
    }

    public function testJobWithObjectResponse(): void
    {
        $workflow = workflow(
            job1: sync(
                function (): TestEntity {
                    return new TestEntity(id: 123);
                }
            ),
            job2: sync(
                function (#[_int(min: 1)] int $id): int {
                    return $id;
                },
                id: response('job1', 'id')
            ),
        );
        $run = run($workflow);
        $this->assertSame(
            123,
            $run->response('job2')->int()
        );
    }

    public function testDependenciesBeforeRunIfConditions(): void
    {
        $workflow = workflow(
            a: sync(fn (): bool => true)
                ->withRunIfNot(true),
            b: sync(fn () => null)
                ->withRunIf(response('a')),
        );
        $run = run($workflow);
        $this->assertSame(
            ['a', 'b'],
            $run->skip()->toArray()
        );
    }

    /**
     * @param array<string, JobInterface> $jobs
     */
    private function assertExpectedRun(array $jobs, array $runArguments, RunInterface $run): void
    {
        foreach ($jobs as $name => $job) {
            $action = $job->action();
            $this->assertSame(
                $action->__invoke(...$runArguments[$name]),
                $run->response($name)->array()
            );
        }
    }
}
