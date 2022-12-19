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

use function Chevere\DataStructure\vectorToArray;
use Chevere\Response\Response;
use Chevere\Tests\_resources\src\TestActionNoParams;
use Chevere\Tests\_resources\src\TestActionParam;
use Chevere\Tests\_resources\src\TestActionParams;
use Chevere\Throwable\Errors\ArgumentCountError;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\Job;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use Chevere\Workflow\Run;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;
use Chevere\Workflow\Workflow;
use PHPUnit\Framework\TestCase;

final class RunTest extends TestCase
{
    public function testConstruct(): void
    {
        $workflow = (new Workflow(new Jobs()))
            ->withAddedJob(
                job: new Job(
                    TestActionParam::class,
                    foo: variable('foo'),
                )
            );
        $arguments = [
            'foo' => 'bar',
        ];
        $run = new Run($workflow, ...$arguments);
        $this->assertMatchesRegularExpression(
            '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i',
            $run->uuid()
        );
        $this->assertSame($workflow, $run->workflow());
        $this->assertSame($arguments, $run->arguments()->toArray());
        $this->expectException(OutOfBoundsException::class);
        $run->getResponse('not-found');
    }

    public function testWithStepResponse(): void
    {
        $workflow = (new Workflow(new Jobs()))
            ->withAddedJob(
                job0: new Job(
                    TestActionParam::class,
                    foo: variable('foo')
                ),
                job1: new Job(
                    TestActionParams::class,
                    foo: variable('baz'),
                    bar: variable('bar')
                )
            );
        $arguments = [
            'foo' => 'hola',
            'bar' => 'mundo',
            'baz' => 'ql',
        ];
        $run = (new Run($workflow, ...$arguments));
        $workflowRunWithStepResponse = $run
            ->withResponse('job0', new Response());
        $this->assertNotSame($run, $workflowRunWithStepResponse);
        $this->assertSame([], $workflowRunWithStepResponse->getResponse('job0')->data());
    }

    public function testWithAddedNotFound(): void
    {
        $workflow = (new Workflow(new Jobs()))
            ->withAddedJob(
                job0: new Job(
                    TestActionParam::class,
                    foo: variable('foo')
                )
            );
        $arguments = [
            'foo' => 'hola',
        ];
        $this->expectException(OutOfBoundsException::class);
        (new Run($workflow, ...$arguments))
            ->withResponse(
                'not-found',
                new Response()
            );
    }

    public function testWithAddedMissingArguments(): void
    {
        $workflow = (new Workflow(new Jobs()))
            ->withAddedJob(
                job0: new Job(TestActionNoParams::class),
                job1: new Job(
                    TestActionParam::class,
                    foo: variable('foo')
                )
            );
        $this->expectException(ArgumentCountError::class);
        (new Run($workflow))
            ->withResponse(
                'job0',
                new Response()
            );
    }

    public function testWithSkip(): void
    {
        $workflow = workflow(
            job1: job(TestActionNoParams::class),
            job2: job(TestActionNoParams::class)
        );
        $run = new Run($workflow);
        $this->assertCount(0, $run->skip());
        $immutable = $run->withSkip('job1', 'job2');
        $this->assertNotSame($run, $immutable);
        $this->assertCount(2, $immutable->skip());
        $this->assertSame(['job1', 'job2'], vectorToArray($immutable->skip()));
        $this->expectException(OverflowException::class);
        $immutable->withSkip('job1');
    }

    public function testWithSkipMissingJob(): void
    {
        $workflow = workflow();
        $run = new Run($workflow);
        $this->expectException(OutOfBoundsException::class);
        $run->withSkip('job1', 'job2');
    }
}
