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

use Chevere\Action\ActionName;
use Chevere\Tests\_resources\src\TestActionNoParamsIntegerResponse;
use Chevere\Workflow\Job;
use Chevere\Workflow\Jobs;
use Chevere\Workflow\Workflow;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\async;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\workflow;

final class FunctionsTest extends TestCase
{
    public function testFunctionWorkflow(): void
    {
        $workflow = workflow();
        $this->assertEquals(new Workflow(new Jobs()), $workflow);
    }

    public function testFunctionSync(): void
    {
        $actionName = new ActionName(TestActionNoParamsIntegerResponse::class);
        $job = sync($actionName->__toString());
        $alt = new Job($actionName, true);
        $this->assertEquals($alt, $job);
    }

    public function testFunctionAsync(): void
    {
        $actionName = new ActionName(TestActionNoParamsIntegerResponse::class);
        $job = async($actionName->__toString());
        $alt = new Job($actionName, false);
        $this->assertEquals($alt, $job);
    }
}
