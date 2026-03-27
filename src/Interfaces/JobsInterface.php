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

use Chevere\DataStructure\Interfaces\MapInterface;
use Chevere\DataStructure\Interfaces\StringMappedInterface;
use Chevere\DataStructure\Interfaces\VectorInterface;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Iterator;
use OutOfBoundsException;
use OverflowException;

/**
 * Describes the component in charge of defining a collection of Jobs.
 *
 * @extends StringMappedInterface<JobInterface>
 */
interface JobsInterface extends StringMappedInterface
{
    /**
     * Determines if the collection contains a job with the given name.
     *
     * @param string $job The job name to check
     * @return bool True if the job exists, false otherwise
     */
    public function has(string $job): bool;

    /**
     * Retrieves a job from the collection by its name.
     *
     * @param string $job The job name to retrieve
     * @return JobInterface The job instance
     * @throws OutOfBoundsException If the job does not exist
     */
    public function get(string $job): JobInterface;

    /**
     * Provides access to all workflow variables used across the jobs.
     *
     * Variables are workflow-level inputs that can be referenced by multiple jobs.
     * This method returns a map where keys are variable names and values are their parameter definitions.
     *
     * @return MapInterface<ParameterInterface> Map of variable name to parameter interface
     */
    public function variables(): MapInterface;

    /**
     * Provides access to all response references used across the jobs.
     *
     * Response references are connections between job outputs and other job inputs.
     * This method returns a map where keys are response reference strings and values are their parameter definitions.
     *
     * @return MapInterface<ParameterInterface> Map of response reference to parameter interface
     */
    public function references(): MapInterface;

    /**
     * Provides access to all job names in the collection.
     *
     * @return string[] Array of job names in the order they were added
     */
    public function keys(): array;

    /**
     * Provides access to the job execution dependency graph.
     *
     * The graph determines the execution order of jobs based on their dependencies
     * and response references. Jobs are organized into execution levels where jobs
     * in the same level can run concurrently.
     *
     * @return GraphInterface The dependency graph for job execution
     */
    public function graph(): GraphInterface;

    public function violations(): VectorInterface;

    /**
     * Return an instance with additional jobs added to the collection.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the additional jobs. Job names are derived
     * from the parameter names used in the method call.
     *
     * @param JobInterface ...$jobs Named jobs to add to the collection
     * @return self New instance with the added jobs
     * @throws OverflowException If a job name already exists in the collection
     */
    public function withAdded(JobInterface ...$jobs): self;

    /**
     * Provides an iterator for traversing all jobs in the collection.
     *
     * Enables foreach iteration over the jobs collection where keys are job names
     * and values are JobInterface instances.
     *
     * @return Iterator<string, JobInterface> Iterator for job name to job instance pairs
     */
    public function getIterator(): Iterator;
}
