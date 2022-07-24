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

use Chevere\Tests\_resources\src\TestActionNoParams;
use Chevere\Throwable\Exceptions\OutOfBoundsException;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use function Chevere\Workflow\reference;
use PHPUnit\Framework\TestCase;

final class JobsTest extends TestCase
{
    public function testAsync(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class),
        );
        $this->assertSame(
            [
                ['j1', 'j2'],
            ],
            $jobs->graph()
        );
    }

    public function testSync(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class)->withIsSync(),
            j2: job(TestActionNoParams::class)->withIsSync(),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2'],
            ],
            $jobs->graph()
        );
    }

    public function testWithDependsOnJob(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class)->withDepends('j1'),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2'],
            ],
            $jobs->graph()
        );
    }

    public function testWithDependsMissing(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/undeclared dependencies\: j0$/');
        new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class)
                    ->withDepends('j0', 'j1'),
        );
    }

    public function testWithDependsOnPreviousChain(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class),
            j3: job(TestActionNoParams::class)
                ->withDepends('j2')
                ->withDepends('j1'),
        );
        $this->assertSame(
            [
                ['j1', 'j2'],
                ['j3'],
            ],
            $jobs->graph()
        );
    }

    public function testWithDependsOnPreviousFunction(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class)
                    ->withDepends('j1'),
            j3: job(TestActionNoParams::class)
                    ->withDepends('j2'),
        );
        $this->assertSame(
            [
                ['j1'],
                ['j2'],
                ['j3'],
            ],
            $jobs->graph()
        );
    }

    public function testWithDependsMix(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParams::class),
            j2: job(TestActionNoParams::class),
            j3: job(TestActionNoParams::class)
                    ->withDepends('j1', 'j2'),
            j4: job(TestActionNoParams::class),
            j5: job(TestActionNoParams::class)
                    ->withDepends('j4'),
            j6: job(TestActionNoParams::class)
                    ->withDepends('j5'),
        );
        $this->assertSame(
            [
                ['j1', 'j2', 'j4'],
                ['j3', 'j5'],
                ['j6'],
            ],
            $jobs->graph()
        );
    }

    public function testWithAdded(): void
    {
        $jobs = new Jobs();
        $this->assertFalse($jobs->has('j1'));
        $withAdded = $jobs->withAdded(
            j1: job(TestActionNoParams::class),
        );
        $this->assertNotSame($jobs, $withAdded);
        $this->assertTrue($withAdded->has('j1'));
    }

    public function testWithRunIfUndeclaredJob(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Job job not found');
        new Jobs(
            j1: job(TestActionNoParams::class)
                ->withRunIf(
                    reference('job', 'parameter')
                ),
        );
    }

    public function testWithRunIfUndeclaredJobKey(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Parameter parameter not found');
        new Jobs(
            j0: job(TestActionNoParams::class),
            j1: job(TestActionNoParams::class)
                ->withRunIf(
                    reference('j0', 'parameter')
                ),
        );
    }
}
