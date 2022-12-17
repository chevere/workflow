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

use Chevere\Container\Container;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ReferenceInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\RunnerInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Psr\Container\ContainerInterface;
use Throwable;

// @codeCoverageIgnoreStart

/**
 * Creates a WorkflowInterface instance for the given jobs.
 */
function workflow(JobInterface ...$job): WorkflowInterface
{
    return new Workflow(
        new Jobs(...$job)
    );
}

/**
 * Creates a JobInterface instance for the given action and arguments.
 *
 * @param string $action Action name
 * @param mixed ...$argument Action arguments (raw, reference or variable)
 */
function job(string $action, mixed ...$argument): JobInterface
{
    return new Job($action, ...$argument);
}

/**
 * Creates a ReferenceInterface instance for the given job and parameter.
 *
 * @param string $job Job name
 * @param string $parameter Response parameter name
 */
function reference(string $job, string $parameter): ReferenceInterface
{
    return new Reference($job, $parameter);
}

/**
 * Creates a VariableInterface instance for the given name.
 *
 * @param string $name Variable name
 */
function variable(string $name): VariableInterface
{
    return new Variable($name);
}

/**
 * Creates a RunnerInterface instance for the given job.
 */
function runnerForJob(RunnerInterface $runner, string $job): RunnerInterface
{
    try {
        $runner->run()->getResponse($job);

        return $runner;
    } catch(Throwable) {
        // ignore
    }

    return $runner->withRunJob($job);
}

/**
 * Creates a RunInterface instance for the given workflow, variables and container.
 *
 * @param array<string, mixed> $variables
 */
function run(
    WorkflowInterface $workflow,
    array $variables = [],
    ?ContainerInterface $container = null
): RunInterface {
    $run = new Run($workflow, ...$variables);

    return (new Runner($run, $container ?? new Container()))
        ->withRun()
        ->run();
}
// @codeCoverageIgnoreEnd
