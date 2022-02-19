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

use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Workflow\Interfaces\WorkflowRunInterface;
use Chevere\Workflow\Jobs;
use Chevere\Workflow\Workflow;
use Chevere\Workflow\WorkflowMessage;
use Chevere\Workflow\WorkflowRun;
use PHPUnit\Framework\TestCase;

final class WorkflowMessageTest extends TestCase
{
    public function getWorkflowRun(): WorkflowRunInterface
    {
        return new WorkflowRun(new Workflow(new Jobs()));
    }

    public function testConstruct(): void
    {
        $run = $this->getWorkflowRun();
        $workflowMessage = new WorkflowMessage($run);
        $this->assertSame(0, $workflowMessage->priority());
        $this->assertSame($run, $workflowMessage->workflowRun());
        $this->assertIsString($workflowMessage->uuid());
        $this->assertSame(0, $workflowMessage->delay());
        $this->assertSame(0, $workflowMessage->expiration());
    }

    public function testWithPriority(): void
    {
        $workflowMessage = new WorkflowMessage($this->getWorkflowRun());
        foreach ([0, 255] as $priority) {
            $new = $workflowMessage->withPriority($priority);
            $this->assertNotSame($workflowMessage, $new);
            $this->assertSame($priority, $new->priority());
        }
        $this->expectException(InvalidArgumentException::class);
        $workflowMessage = $workflowMessage->withPriority(-999);
    }

    public function testWithPriorityOutOfRange(): void
    {
        $workflowMessage = new WorkflowMessage($this->getWorkflowRun());
        $this->expectException(InvalidArgumentException::class);
        $workflowMessage->withPriority(256);
    }

    public function testWithDelay(): void
    {
        $workflowMessage = new WorkflowMessage($this->getWorkflowRun());
        foreach ([0, 3600] as $delay) {
            $new = $workflowMessage->withDelay($delay);
            $this->assertNotSame($workflowMessage, $new);
            $this->assertSame($delay, $new->delay());
        }
        $this->expectException(InvalidArgumentException::class);
        $workflowMessage = $workflowMessage->withDelay(-3600);
    }

    public function testWithExpiration(): void
    {
        $workflowMessage = new WorkflowMessage($this->getWorkflowRun());
        $ahead = 60;
        $workflowMessageWithExpiration = $workflowMessage->withExpiration($ahead);
        $this->assertNotSame($workflowMessage, $workflowMessageWithExpiration);
        $this->assertSame($ahead, $workflowMessageWithExpiration->expiration());
        $this->expectException(InvalidArgumentException::class);
        $workflowMessage = $workflowMessage->withExpiration(-60);
    }
}
