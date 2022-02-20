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
    public function testWea(): void
    {
        $jobs = new Jobs(
            j1: job(ActionTestAction::class),
            j2: job(ActionTestAction::class),
            j3: job(ActionTestAction::class)->withDependencies('j1'),
        );
        // vdd($jobs->jobDependencies(), $jobs->jobDependencies()->getGraph());
    }
}
