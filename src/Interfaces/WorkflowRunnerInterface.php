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
 * Describes the component in charge of doing.
 */
interface WorkflowRunnerInterface
{
    public function __construct(WorkflowRunInterface $workflowRun);

    public function workflowRun(): WorkflowRunInterface;

    public function withRun(ContainerInterface $container): WorkflowRunnerInterface;

    public function runJob(string $name): void;
}
