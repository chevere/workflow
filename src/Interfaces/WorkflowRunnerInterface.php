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

use Psr\Container\ContainerInterface;

/**
 * Describes the component in charge of running the workflow.
 */
interface WorkflowRunnerInterface
{
    public function __construct(
        WorkflowRunInterface $workflowRun,
        ContainerInterface $container
    );

    public function workflowRun(): WorkflowRunInterface;

    public function withRun(): WorkflowRunnerInterface;

    public function withRunJob(string $name): WorkflowRunnerInterface;
}
