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

namespace Chevere\Tests\Workflow;

use function Chevere\Workflow\getWorkflowMessage;
use Chevere\Workflow\Steps;
use Chevere\Workflow\Workflow;
use Chevere\Workflow\WorkflowResponse;
use PHPUnit\Framework\TestCase;

final class WorkflowResponseTest extends TestCase
{
    public function testWithWorkflowMessage(): void
    {
        $data = [];
        $workflowMessage = getWorkflowMessage(new Workflow(new Steps()))
            ->withDelay(123)
            ->withExpiration(111)
            ->withPriority(10);
        $workflowResponse = new WorkflowResponse();
        $workflowResponseWithMessage = $workflowResponse
            ->withWorkflowMessage($workflowMessage);
        $this->assertNotSame($workflowResponse, $workflowResponseWithMessage);
        $this->assertSame($workflowMessage, $workflowResponseWithMessage->workflowMessage());
        $this->assertSame($data, $workflowResponseWithMessage->data());
    }
}
