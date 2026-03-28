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

namespace Chevere\Workflow\Traits;

use BadMethodCallException;
use Chevere\Container\Container;
use Chevere\Workflow\Interfaces\RunInterface;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Psr\Container\ContainerInterface;
use function Chevere\Workflow\run;

/**
 * Provides method `execute()` to assign `$run` property.
 * Provides method `run()` to access the `$run` property.
 */
trait WorkflowTrait
{
    private RunInterface $run;

    /**
     * Access the `$run` property.
     *
     * ```php
     * $this->run();
     * ```
     */
    public function run(): RunInterface
    {
        return $this->run
            ??= throw new BadMethodCallException();
    }

    /**
     * Run the given workflow with the provided arguments.
     *
     * ```php
     * $this->execute($workflow, $container, name: 'value',...);
     * ```
     */
    private function execute(
        WorkflowInterface $workflow,
        ContainerInterface $container = new Container(),
        mixed ...$argument
    ): void {
        $this->run = run($workflow, $container, ...$argument);
    }
}
