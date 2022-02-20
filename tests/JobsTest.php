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

use Chevere\Tests\_resources\src\ActionTestAction;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use PHPUnit\Framework\TestCase;

final class JobsTest extends TestCase
{
    public function testParallel(): void
    {
        $jobs = new Jobs(
            j1: job(ActionTestAction::class),
            j2: job(ActionTestAction::class),
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
            j1: job(ActionTestAction::class),
            j2: job(ActionTestAction::class)->withDepends('j1'),
        );
        $this->assertSame(
            [
                0 => ['j1'],
                1 => ['j2'],
            ],
            $jobs->getGraph()
        );
    }

    public function testWithDependsOnPrevious(): void
    {
        $jobs = new Jobs(
            j1: job(ActionTestAction::class),
            j2: job(ActionTestAction::class)->withDepends('j1'),
        );
        $this->assertSame(
            [
                0 => ['j1'],
                1 => ['j2'],
            ],
            $jobs->getGraph()
        );
    }

    public function testWithDependsOnPreviousChain(): void
    {
        $jobs = new Jobs(
            j1: job(ActionTestAction::class),
            j2: job(ActionTestAction::class),
            j3: job(ActionTestAction::class)
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
            j1: job(ActionTestAction::class),
            j2: job(ActionTestAction::class)->withDepends('j1'),
            j3: job(ActionTestAction::class)->withDepends('j2'),
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
            j1: job(ActionTestAction::class),
            j2: job(ActionTestAction::class),
            j3: job(ActionTestAction::class)
                ->withDepends('j1', 'j2'),
            j4: job(ActionTestAction::class),
            j5: job(ActionTestAction::class)->withDepends('j4'),
            j6: job(ActionTestAction::class)->withDepends('j5'),
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
