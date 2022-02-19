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

use Chevere\DataStructure\Map;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Throwable\Exceptions\OverflowException;
use Countable;

/**
 * Describes the component in charge of defining a collection of chained tasks.
 */
interface WorkflowInterface extends Countable
{
    public const REGEX_PARAMETER_REFERENCE = '/^\${([\w-]*)}$/';

    public const REGEX_JOB_REFERENCE = '/^\${([\w-]*)\:([\w-]*)}$/';

    public function __construct(JobsInterface $jobs);

    public function jobs(): JobsInterface;

    public function vars(): Map;

    /**
     * Return an instance with the specified `$job`.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified `$job`.
     *
     * @throws OverflowException
     */
    public function withAddedJob(JobInterface ...$jobs): self;

    /**
     * Return an instance with the specified `$job` added before `$before`.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified `$job` added before `$before`.
     *
     * @throws OverflowException
     */
    public function withAddedJobBefore(string $before, JobInterface ...$jobs): self;

    /**
     * Return an instance with the specified `$job` added after `$after`.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the specified `$job` added after `$after`.
     *
     * @throws OverflowException
     */
    public function withAddedJobAfter(string $after, JobInterface ...$jobs): self;

    public function parameters(): ParametersInterface;

    /**
     * Provides access to the `$var` mapping for job variables.
     *
     * Case `${foo}` (workflow variables):
     *
     * ```php
     * return ['foo'];
     * ```
     *
     * Case `${step:var}` (named step responses):
     *
     * ```php
     * return ['step', 'var'];
     * ```
     *
     * @return string[]
     */
    public function getVar(string $var): array;

    /**
     * Provides access to the expected return arguments for the given `$job`.
     */
    public function getProvided(string $job): ParametersInterface;
}
