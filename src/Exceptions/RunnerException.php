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
use Throwable;

/**
 * Exception thrown by the Workflow runner (dynamic).
 */
final class RunnerException extends WorkflowException
{
    public readonly int $attempt;

    private int $maxAttempts;

    public function __construct(
        string $name,
        JobInterface $job,
        Throwable $throwable,
        int $attempt,
    ) {
        $this->attempt = $attempt;
        $this->maxAttempts = $job->retryPolicy()
            ->maxAttempts();
        parent::__construct(
            name: $name,
            job: $job,
            throwable: $throwable,
        );
    }

    protected function template(): string
    {
        if ($this->maxAttempts > 1) {
            return '[%name%]: [' . $this->attempt . '/' . $this->maxAttempts . '] %message%';
        }

        return '[%name%]: %message%';
    }
}
