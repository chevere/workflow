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

use Chevere\Action\Interfaces\ActionInterface;
use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\RunnerInterface;
use Chevere\Workflow\Interfaces\VariableInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
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
 * @param mixed ...$argument Action arguments for its run method (raw, reference or variable)
 */
function sync(ActionInterface $action, mixed ...$argument): JobInterface
{
    return new Job($action, true, ...$argument);
}

/**
 * Creates an asynchronous job for the given action and arguments.
 *
 * @param mixed ...$argument Action arguments for its run method (raw, reference or variable)
 */
function async(ActionInterface $action, mixed ...$argument): JobInterface
{
    return new Job($action, false, ...$argument);
}

/**
 * Creates a reference to the response for the given job and key (if any).
 *
 * @param string $job Job
 * @param ?string $key Job response key (optional)
 */
function response(string $job, ?string $key = null): ResponseReferenceInterface
{
    return new ResponseReference($job, $key);
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
        $runner->run()->getReturn($job);

        return $runner;
    } catch (Throwable) {
        // ignore
    }

    return $runner->withRunJob($job);
}

/**
 * Creates a RunInterface instance for the given workflow and variables .
 */
function run(
    WorkflowInterface $workflow,
    mixed ...$variable,
): RunInterface {
    $run = new Run($workflow, ...$variable);
    $runner = new Runner($run);

    return $runner->withRun()->run();
}
// @codeCoverageIgnoreEnd
