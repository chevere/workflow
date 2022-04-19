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

use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use PHPUnit\Framework\TestCase;

final class JobsTest extends TestCase
{
    public function testParallel(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParamsIntegerResponse::class),
            j2: job(TestActionNoParamsIntegerResponse::class),
        );
        $this->assertSame(
            [
                0 => ['j1', 'j2'],
            ],
            $jobs->getGraph()
        );
    }

    public function testWithDependsOnJob(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParamsIntegerResponse::class),
            j2: job(TestActionNoParamsIntegerResponse::class)->withDepends('j1'),
        );
        $this->assertSame(
            [
                0 => ['j1'],
                1 => ['j2'],
            ],
            $jobs->getGraph()
        );
    }

    public function testWithDependsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Jobs(
            j1: job(TestActionNoParamsIntegerResponse::class)->withDepends('j0'),
        );
    }

    public function testWithDependsOnPreviousChain(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParamsIntegerResponse::class),
            j2: job(TestActionNoParamsIntegerResponse::class),
            j3: job(TestActionNoParamsIntegerResponse::class)
                ->withDepends('j2')
                ->withDepends('j1'),
        );
        $this->assertSame(
            [
                0 => ['j1', 'j2'],
                1 => ['j3'],
            ],
            $jobs->getGraph()
        );
    }

    public function testWithDependsOnPreviousFunction(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParamsIntegerResponse::class),
            j2: job(TestActionNoParamsIntegerResponse::class)->withDepends('j1'),
            j3: job(TestActionNoParamsIntegerResponse::class)->withDepends('j2'),
        );
        $this->assertSame(
            [
                0 => ['j1'],
                1 => ['j2'],
                2 => ['j3'],
            ],
            $jobs->getGraph()
        );
    }

    public function testWithDependsMix(): void
    {
        $jobs = new Jobs(
            j1: job(TestActionNoParamsIntegerResponse::class),
            j2: job(TestActionNoParamsIntegerResponse::class),
            j3: job(TestActionNoParamsIntegerResponse::class)
                ->withDepends('j1', 'j2'),
            j4: job(TestActionNoParamsIntegerResponse::class),
            j5: job(TestActionNoParamsIntegerResponse::class)->withDepends('j4'),
            j6: job(TestActionNoParamsIntegerResponse::class)->withDepends('j5'),
        );
        $this->assertSame(
            [
                0 => ['j1', 'j2', 'j4'],
                1 => ['j3', 'j5'],
                2 => ['j6'],
            ],
            $jobs->getGraph()
        );
    }
}
