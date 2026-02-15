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

use Chevere\Parameter\Interfaces\StringParameterInterface;
use Chevere\Tests\src\TestActionParamsReturn;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\async;
use function Chevere\Workflow\response;
use function Chevere\Workflow\run;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;

final class WorkflowPositionalArgumentsTest extends TestCase
{
    public function testPositionalArgumentsMapping(): void
    {
        $arguments = ['first', 'second'];
        $job = async(TestActionParamsReturn::class, ...$arguments);
        $this->assertSame($arguments, $job->arguments());
        $workflow = workflow(job1: $job);
        $run = run($workflow);
        $this->assertSame(
            [
                'foo' => 'first',
                'bar' => 'second',
            ],
            $run->response('job1')->array()
        );
    }

    public function testPositionalArgumentsWithVariable(): void
    {
        $job = async(TestActionParamsReturn::class, variable('myVar'), 'value2');
        $workflow = workflow(job1: $job);
        $this->assertTrue($workflow->parameters()->has('myVar'));
        $run = run($workflow, myVar: 'chevere');
        $this->assertSame(
            [
                'foo' => 'chevere',
                'bar' => 'value2',
            ],
            $run->response('job1')->array()
        );
    }

    public function testPositionalVariableIsRegisteredWithCorrectParameterType(): void
    {
        $workflow = workflow(
            job1: async(
                function (string $foo, int $bar): array {
                    return [];
                },
                variable('vv'), // $foo
                123             // $bar
            )
        );
        $this->assertTrue($workflow->parameters()->has('vv'));
        $this->assertInstanceOf(
            StringParameterInterface::class,
            $workflow->parameters()->get('vv')
        );
    }

    public function testPositionalArgumentsWithResponse(): void
    {
        $job1 = async(TestActionParamsReturn::class, 'a', 'b');
        $job2 = async(TestActionParamsReturn::class, response('job1', 'bar'), 'c');

        $workflow = workflow(job1: $job1, job2: $job2);
        $run = run($workflow);
        $this->assertSame(
            [
                'foo' => 'b',
                'bar' => 'c',
            ],
            $run->response('job2')->array()
        );
    }

    public function testMixedNamedAndPositionalArguments(): void
    {
        $job1 = async(TestActionParamsReturn::class, 'pos1', 'pos2');
        $job2 = async(TestActionParamsReturn::class, foo: 'named1', bar: 'named2');
        $workflow = workflow(job1: $job1, job2: $job2);
        $run = run($workflow);
        $this->assertSame(
            [
                'foo' => 'pos1',
                'bar' => 'pos2',
            ],
            $run->response('job1')->array()
        );
        $this->assertSame(
            [
                'foo' => 'named1',
                'bar' => 'named2',
            ],
            $run->response('job2')->array()
        );
    }
}
