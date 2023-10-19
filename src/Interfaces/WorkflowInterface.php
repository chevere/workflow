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

use Chevere\Parameter\Interfaces\ParameterInterface;
use Chevere\Parameter\Interfaces\ParametersInterface;
use Chevere\Throwable\Exceptions\OverflowException;
use Countable;

/**
 * Describes the component in charge of defining a collection of chained tasks.
 */
interface WorkflowInterface extends Countable
{
    public function jobs(): JobsInterface;

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

    /**
     * Provides access to the expected return parameter for the given `$job`.
     */
    public function getJobResponseParameter(string $job): ParameterInterface;
}
