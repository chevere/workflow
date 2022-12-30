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
use function Chevere\Workflow\async;
use Chevere\Workflow\Job;
use Chevere\Workflow\Jobs;
use function Chevere\Workflow\sync;
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

    public function testFunctionSync(): void
    {
        $args = [
            'action' => new TestActionNoParamsIntegerResponse(),
        ];
        $job = sync(...$args);
        $args['isSync'] = true;
        $this->assertEquals(new Job(...$args), $job);
    }

    public function testFunctionAsync(): void
    {
        $args = [
            'action' => new TestActionNoParamsIntegerResponse(),
        ];
        $job = async(...$args);
        $args['isSync'] = false;
        $this->assertEquals(new Job(...$args), $job);
    }
}
