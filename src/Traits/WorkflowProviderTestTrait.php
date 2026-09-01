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

use Chevere\Workflow\Interfaces\WorkflowInterface;
use Chevere\Workflow\Interfaces\WorkflowProviderInterface;

trait WorkflowProviderTestTrait
{
    abstract public static function assertTrue(mixed $condition, string $message = ''): void;

    abstract public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void;

    public function assertWorkflowProvider(string|object $className): void
    {
        $context = match (true) {
            is_string($className) => "Class {$className}",
            default => sprintf('Object of class %s', get_class($className)),
        };
        $this->assertTrue(
            is_subclass_of($className, WorkflowProviderInterface::class, true),
            sprintf('%s does not implement WorkflowProviderInterface', $context)
        );
    }

    /**
     * @param class-string<WorkflowProviderInterface>|WorkflowInterface $workflow
     */
    public function assertWorkflowGraph(array $expected, string|WorkflowInterface $workflow): void
    {
        if (is_string($workflow)) {
            $this->assertWorkflowProvider($workflow);
            $workflow = $workflow::workflow();
        }
        $this->assertSame(
            $expected,
            $workflow->jobs()
                ->graph()
                ->toArray(),
            'Workflow graph does not match expected structure'
        );
    }
}
