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
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Chevere\Workflow\Interfaces\WorkflowMessageInterface;
use Chevere\Workflow\Interfaces\WorkflowRunInterface;
use Chevere\Workflow\Interfaces\WorkflowRunnerInterface;
use Psr\Container\ContainerInterface;

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

function workflowRunner(
    WorkflowRunnerInterface $workflowRunner,
    string $job,
): WorkflowRunnerInterface {
    $run = $workflowRunner->workflowRun();
    if ($run->has($job)) {
        return $workflowRunner;
    }
    $workflowRunner->runJob($job);

    return $workflowRunner;
}

function workflowRun(
    WorkflowInterface $workflow,
    array $vars = [],
    ?ContainerInterface $container = null
): WorkflowRunInterface {
    $workflowRun = new WorkflowRun($workflow, ...$vars);

    return (new WorkflowRunner(
        $workflowRun,
        $container ?? new Container()
    ))
        ->withRun()
        ->workflowRun();
}
