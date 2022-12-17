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

use Chevere\DataStructure\Interfaces\MappedInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Response\Interfaces\ResponseInterface;
use Chevere\Throwable\Errors\ArgumentCountError;
use Iterator;

/**
 * Describes the component in charge of defining a workflow run, with arguments returned for each job.
 */
interface RunInterface extends MappedInterface
{
    public function keys(): array;

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
     * @return VectorInterface<string>
     */
    public function skip(): VectorInterface;

    /**
     * @throws ArgumentCountError
     */
    public function withResponse(string $job, ResponseInterface $response): self;

    public function withSkip(string ...$job): self;

    /**
     * Provides access to the ResponseInterface instance for the given `$job`.
     */
    public function getResponse(string $job): ResponseInterface;

    /**
     * Iterator for job responses.
     *
     * @return Iterator<string, ResponseInterface>
     */
    public function getIterator(): Iterator;
}
