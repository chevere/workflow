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
use Chevere\Workflow\Job;
use function Chevere\Workflow\job;
use Chevere\Workflow\Jobs;
use function Chevere\Workflow\workflow;
use Chevere\Workflow\Workflow;
use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase
{
    public function testFunctionWorkflow(): void
    {
        $workflow = workflow();
        $this->assertEquals(new Workflow(new Jobs()), $workflow);
    }

    public function testFunctionJob(): void
    {
        $args = [
            'action' => TestActionNoParamsIntegerResponse::class,
        ];
        $job = job(...$args);
        $this->assertEquals(new Job(...$args), $job);
    }
}
