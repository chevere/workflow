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

use Chevere\Workflow\Interfaces\JobInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Chevere\Workflow\Interfaces\WorkflowMessageInterface;
use Chevere\Workflow\Interfaces\WorkflowRunnerInterface;

function workflow(JobInterface ...$namedSteps): WorkflowInterface
{
    return new Workflow(
        new Jobs(...$namedSteps)
    );
}

function job(
    string $action,
    mixed ...$namedArguments
): JobInterface {
    return new Job($action, ...$namedArguments);
}

/**
 * @codeCoverageIgnore
 */
function getWorkflowMessage(WorkflowInterface $workflow, mixed ...$namedArguments): WorkflowMessageInterface
{
    return new WorkflowMessage(new WorkflowRun($workflow, ...$namedArguments));
}

/**
 * Push `$workflowQueue` to the queue
 * @codeCoverageIgnore
 */
function pushWorkflowQueue(WorkflowMessageInterface $workflowMessage, $stack): void
{
    $stack->push($workflowMessage);
    $stack[] = $workflowMessage;
}

function runJob(
    WorkflowRunnerInterface $workflowRunner,
    string $name,
): WorkflowRunnerInterface {
    if ($workflowRunner->workflowRun()->has($name)) {
        return $workflowRunner;
    }
    $job = $workflowRunner->workflowRun()->workflow()->jobs()->get($name);
    $workflowRunner->runJob($name, $job);

    return $workflowRunner;
}
