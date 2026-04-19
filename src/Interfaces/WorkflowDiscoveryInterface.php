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

use RuntimeException;

/**
 * Describes the component in charge of discovering workflow providers and their dependencies.
 */
interface WorkflowDiscoveryInterface
{
    public const PROVIDERS_FILENAME = 'workflow-providers.php';

    public const DEPENDENCIES_FILENAME = 'workflow-dependencies.php';

    /**
     * @return array<class-string<WorkflowProviderInterface>> List of provider class names.
     */
    public function providers(): array;

    /**
     * @return array<class-string> List of class names defined by job action requiring constructor dependencies.
     */
    public function dependencies(): array;

    /**
     * Stores the discovery results in PHP files at the specified directory.
     *
     * - `workflow-providers.php`: Array of discovered provider class names.
     * - `workflow-dependencies.php`: Array of dependency class names required by jobs.
     *
     * @param string $dir Directory where the discovery results will be stored.
     */
    public function build(string $dir): void;

    /**
     * Creates a ProviderDiscovery instance by scanning the specified directory for workflow providers.
     *
     * @param string $dir Directory to scan for workflow providers.
     * @throws RuntimeException If the directory cannot be read or is invalid.
     */
    public static function fromDirectory(string $dir): self;

    /**
     * Creates a ProviderDiscovery instance by loading discovery results from the specified cache directory.
     *
     * @param string $dir Directory where the discovery cache files are located.
     * @throws RuntimeException If the cache files cannot be read or are invalid.
     */
    public static function fromBuild(string $dir): self;
}
