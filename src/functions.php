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

function workflow(JobInterface ...$job): WorkflowInterface
{
    return new Workflow(
        new Jobs(...$job)
    );
}

function job(
    string $action,
    mixed ...$namedArguments
): JobInterface {
    return new Job($action, ...$namedArguments);
}

/**
 * @param string $job Job name
 * @param string $parameter Response parameter name
 */
function reference(string $job, string $parameter): ReferenceInterface
{
    return new Reference($job, $parameter);
}

function variable(string $name): VariableInterface
{
    return new Variable($name);
}

function runnerForJob(RunnerInterface $runner, string $job): RunnerInterface
{
    if ($runner->run()->has($job)) {
        return $runner;
    }

    return $runner->withRunJob($job);
}

/**
 * @param Array<string, mixed> $variables
 */
function run(
    WorkflowInterface $workflow,
    array $variables = [],
    ?ContainerInterface $container = null
): RunInterface {
    $workflowRun = new Run($workflow, ...$variables);

    return (new Runner(
        $workflowRun,
        $container ?? new Container()
    ))
        ->withRun()
        ->run();
}
