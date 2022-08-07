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
interface RunnerInterface
{
    public function __construct(
        RunInterface $run,
        ContainerInterface $container
    );

    public function run(): RunInterface;

    public function withRun(): RunnerInterface;

    public function withRunJob(string $name): RunnerInterface;
}
