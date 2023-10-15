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

use Chevere\Action\ActionName;
use Chevere\Action\Interfaces\ActionInterface;
use Chevere\Container\Container;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
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
 * Creates a synchronous job for the given action and arguments.
 *
 * @param class-string<ActionInterface> $action
 * @param mixed ...$argument Action arguments for its run method (raw, reference or variable)
 */
function sync(string $action, mixed ...$argument): JobInterface
{
    $action = new ActionName($action);

    return new Job($action, true, ...$argument);
}

/**
 * Creates an asynchronous job for the given action and arguments.
 *
 * @param class-string<ActionInterface> $action
 * @param mixed ...$argument Action arguments for its run method (raw, reference or variable)
 */
function async(string $action, mixed ...$argument): JobInterface
{
    return new Job(
        new ActionName($action),
        false,
        ...$argument
    );
}

/**
 * Creates a reference to the response for the given job and key.
 *
 * @param string $job Job
 */
function responseKey(string $job, string $key): ResponseReferenceInterface
{
    return new ResponseReference($job, $key);
}

/**
 * Creates a reference to the response for the given job.
 *
 * @param string $job Job
 */
function response(string $job): ResponseReferenceInterface
{
    return new ResponseReference($job, null);
}

/**
 * Creates a workflow variable.
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
    } catch (Throwable) {
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
