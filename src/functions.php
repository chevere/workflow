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
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\RunnerInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
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

function runnerForJob(
    RunnerInterface $workflowRunner,
    string $job,
): RunnerInterface {
    $run = $workflowRunner->run();
    if ($run->has($job)) {
        return $workflowRunner;
    }

    return $workflowRunner->withRunJob($job);
}

/**
 * @param Array<string, mixed> $vars
 */
function run(
    WorkflowInterface $workflow,
    array $vars = [],
    ?ContainerInterface $container = null
): RunInterface {
    $workflowRun = new Run($workflow, ...$vars);

    return (new Runner(
        $workflowRun,
        $container ?? new Container()
    ))
        ->withRun()
        ->run();
}
