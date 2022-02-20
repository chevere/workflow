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

use Chevere\DataStructure\Interfaces\MapInterface;

/**
 * Describes the component in charge of doing.
 */
interface WorkflowRunnerInterface
{
    public function __construct(WorkflowRunInterface $workflowRun);

    public function workflowRun(): WorkflowRunInterface;

    public function withRun(MapInterface $serviceContainer): WorkflowRunnerInterface;

    public function runJob(string $name, JobInterface $job): void;
}
