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

namespace Chevere\Workflow\Interfaces;

use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Errors\ArgumentCountError;

/**
 * Describes the component in charge of defining a workflow run, with arguments returned for each job.
 */
interface RunInterface
{
    /**
     * @param mixed ...$variables Workflow variables.
     */
    public function __construct(WorkflowInterface $workflow, mixed ...$variables);

    /**
     * Provides access to workflow uuid V4 (RFC 4122).
     * https://tools.ietf.org/html/rfc4122
     */
    public function uuid(): string;

    /**
     * Provides access to the workflow instance.
     */
    public function workflow(): WorkflowInterface;

    /**
     * Provides access to the arguments instance.
     */
    public function arguments(): ArgumentsInterface;

    /**
     * @throws ArgumentCountError
     */
    public function withJobResponse(string $job, ResponseInterface $response): self;

    /**
     * Indicates whether the instance has the given `$job`. Will return `true` if job has ran.
     */
    public function has(string $job): bool;

    /**
     * Provides access to the ResponseInterface instance for the given `$job`.
     */
    public function get(string $job): ResponseInterface;
}
