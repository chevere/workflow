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

use Chevere\Container\Interfaces\DependenciesInterface;
use Chevere\DataStructure\Interfaces\MapInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Countable;
use OverflowException;

/**
 * Describes the component in charge of defining a collection of chained tasks.
 */
interface WorkflowInterface extends Countable
{
    public function jobs(): JobsInterface;

    /**
     * Provides a map of response identifiers referenced from each *producer* job.
     *
     * Shape:
     * - key: `string` — producer job name (e.g. `"job1"`).
     * - value: `string[]` — ordered list of identifiers other jobs reference from that
     *   producer. Each identifier is either a response *key* (when a consumer
     *   references `response('job', 'key')`) or the producer job name itself
     *   (when a consumer references `response('job')` meaning the whole response).
     *
     * Notes:
     * - Entries are appended in processing order and duplicates are possible.
     * - This map answers "which response identifiers are referenced from job X?" —
     *   to find "which jobs reference job X?" inspect `jobs()->get($name)->dependencies()`
     *   or build a reverse mapping from job dependencies.
     *
     * Example:
     * ```php
     * // job2 uses response('job1', 'response1')
     * // job3 uses response('job1')
     * $workflow->referenced()->get('job1'); // ['response1', 'job1']
     * ```
     *
     * @return MapInterface<string[]>
     */
    public function referenced(): MapInterface;

    public function dependencies(): DependenciesInterface;

    /**
     * Return an instance with the specified `$job`.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified `$job`.
     *
     * @throws OverflowException
     */
    public function withAddedJob(JobInterface ...$job): self;

    public function parameters(): ParametersInterface;

    public function lint(): string;
}
