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

use Chevere\Container\Interfaces\ContainerInterface;
use Chevere\DataStructure\Interfaces\StringMappedInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\Parameter\Interfaces\ArgumentsInterface;
use Chevere\Parameter\Interfaces\TypedInterface;

/**
 * Describes the component in charge of defining a workflow run, with arguments returned for each job.
 *
 * @extends StringMappedInterface<TypedInterface>
 */
interface RunInterface extends StringMappedInterface
{
    /**
     * @return array<string> Names for jobs with responses.
     */
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
     * Provides access to the container instance.
     */
    public function container(): ContainerInterface;

    /**
     * Provides access to the arguments instance.
     */
    public function arguments(): ArgumentsInterface;

    /**
     * @return VectorInterface<string>
     */
    public function skip(): VectorInterface;

    /**
     * Return an instance with the specified job response.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified job response.
     */
    public function withResponse(string $job, TypedInterface $response): self;

    /**
     * Return an instance with the specified job names skipped.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified job names skipped.
     */
    public function withSkip(string ...$job): self;

    /**
     * Provides access to the TypedInterface instance for the given `$job`.
     */
    public function response(string $job, string|int ...$key): TypedInterface;

    /**
     * @deprecated Use `response` instead.
     */
    public function getReturn(string $job): TypedInterface;

    /**
     * @return array<string, mixed> Returns all jobs and their raw return.
     */
    public function toArray(): array;
}
