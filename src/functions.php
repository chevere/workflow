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

use Chevere\Workflow\Interfaces\StepInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Chevere\Workflow\Interfaces\WorkflowMessageInterface;

function workflow(StepInterface ...$namedSteps): WorkflowInterface
{
    return new Workflow(
        new Steps(...$namedSteps)
    );
}

function step(string $action, mixed ...$namedArguments): StepInterface
{
    return new Step($action, ...$namedArguments);
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
