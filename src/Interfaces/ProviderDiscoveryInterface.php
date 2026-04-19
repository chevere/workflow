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

/**
 * Describes the component in charge of discovering workflow providers and their dependencies.
 */
interface ProviderDiscoveryInterface
{
    /**
     * @return array<class-string<WorkflowProviderInterface>> List of provider class names.
     */
    public function providers(): array;

    /**
     * @return array<class-string> List of dependency class names defined by job action.
     */
    public function dependencies(): array;

    /**
     * Stores the discovery results in PHP files at the specified directory.
     *
     * - `workflow-providers.php`: Array of discovered provider class names.
     * - `workflow-dependencies.php`: Array of dependency class names required by jobs.
     *
     * @param string|null $dir If null, store files in the same directory specified for discovery lookup.
     */
    public function build(?string $dir = null): void;
}
