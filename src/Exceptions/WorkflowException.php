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

namespace Chevere\Workflow\Exceptions;

use Chevere\Workflow\Interfaces\JobInterface;
use Exception;
use Throwable;
use function Chevere\Message\message;

/**
 * Exception thrown by a Workflow participant.
 */
abstract class WorkflowException extends Exception
{
    /**
     * The job name that thrown the exception.
     */
    public readonly string $name;

    /**
     * The job that thrown the exception.
     */
    public readonly JobInterface $job;

    /**
     * The exception thrown by the job.
     */
    public readonly Throwable $throwable;

    public function __construct(
        string $name,
        JobInterface $job,
        Throwable $throwable,
    ) {
        $message = (string) message(
            $this->template(),
            name: $name,
            message: $throwable->getMessage(),
            caller: $job->caller()
        );
        parent::__construct(message: $message, previous: $throwable);
        $this->name = $name;
        $this->job = $job;
        $this->throwable = $throwable;
        $this->file = $job->caller()
            ->file();
        $this->line = $job->caller()
            ->line();
    }

    /**
     * @infection-ignore-all
     */
    protected function template(): string
    {
        return '[%name%]: %message%';
    }
}
