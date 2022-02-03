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

namespace Chevere\Workflow;

use Chevere\Message\Message;
use Chevere\Workflow\Interfaces\WorkflowMessageInterface;
use Chevere\Workflow\Interfaces\WorkflowRunInterface;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final class WorkflowMessage implements WorkflowMessageInterface
{
    private string $uuid;

    private int $priority;

    private int $delay;

    private int $expiration;

    public function __construct(
        private WorkflowRunInterface $workflowRun
    ) {
        $this->uuid = Uuid::uuid4()->toString();
        $this->priority = 0;
        $this->delay = 0;
        $this->expiration = 0;
    }

    public function withPriority(int $priority): WorkflowMessageInterface
    {
        if ($priority < 0 || $priority > 255) {
            throw new InvalidArgumentException(
                (new Message('Expecting a priority value in the integer range %range%, value %provided% provided'))
                    ->strong('%range%', '0,255')
                    ->code('%provided%', (string) $priority)
            );
        }
        $new = clone $this;
        $new->priority = $priority;

        return $new;
    }

    public function withDelay(int $seconds): WorkflowMessageInterface
    {
        $this->assertTime($seconds);
        $new = clone $this;
        $new->delay = $seconds;

        return $new;
    }

    public function withExpiration(int $seconds): WorkflowMessageInterface
    {
        $this->assertTime($seconds);
        $new = clone $this;
        $new->expiration = $seconds;

        return $new;
    }

    public function workflowRun(): WorkflowRunInterface
    {
        return $this->workflowRun;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function delay(): int
    {
        return $this->delay;
    }

    public function expiration(): int
    {
        return $this->expiration;
    }

    private function assertTime(int $time): void
    {
        if ($time < 0) {
            throw new InvalidArgumentException(
                (new Message("Time provided can't be negative"))
            );
        }
    }
}
