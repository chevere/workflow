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

use Chevere\Filesystem\Interfaces\DirectoryInterface;
use Chevere\VarSupport\StorableVariable;
use Chevere\Workflow\Interfaces\ProviderDiscoveryInterface;
use Chevere\Workflow\Interfaces\WorkflowProviderInterface;
use RuntimeException;
use Spatie\StructureDiscoverer\Discover;
use function Chevere\Filesystem\directoryForPath;
use function Chevere\Filesystem\filePhpReturnForPath;

final class ProviderDiscovery implements ProviderDiscoveryInterface
{
    /**
     * @param array<class-string<WorkflowProviderInterface>> $providers
     * @param array<class-string> $dependencies
     */
    public function __construct(
        public readonly array $providers,
        public readonly array $dependencies
    ) {
    }

    public function providers(): array
    {
        return $this->providers;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function build(string $dir): void
    {
        $directory = static::getDirectory($dir);
        filePhpReturnForPath(
            $directory->path()->getChild(static::PROVIDERS_FILENAME)
        )
            ->put(
                new StorableVariable($this->providers)
            );
        filePhpReturnForPath(
            $directory->path()->getChild(static::DEPENDENCIES_FILENAME)
        )
            ->put(
                new StorableVariable($this->dependencies)
            );
    }

    public static function fromDirectory(string $dir): ProviderDiscoveryInterface
    {
        $directory = static::getDirectory($dir);
        /** @var array<class-string<WorkflowProviderInterface>> $providers */
        $providers = Discover::in($directory->path()->__toString())
            ->classes()->implementing(WorkflowProviderInterface::class)
            ->get();
        sort($providers);
        $dependencies = [];
        foreach ($providers as $providerClass) {
            foreach ($providerClass::workflow()->dependencies()->classes() as $dependencyClass) {
                $dependencies[$dependencyClass] = true;
            }
        }
        /** @var array<class-string> $dependencies */
        $dependencies = array_keys($dependencies);
        sort($dependencies);

        return new self($providers, $dependencies);
    }

    public static function fromBuild(string $dir): ProviderDiscoveryInterface
    {
        $directory = static::getDirectory($dir);
        /** @var array<class-string<WorkflowProviderInterface>> $providers */
        $providers = filePhpReturnForPath(
            $directory->path()->getChild(static::PROVIDERS_FILENAME)
        )->get();
        /** @var array<class-string> $dependencies */
        $dependencies = filePhpReturnForPath(
            $directory->path()->getChild(static::DEPENDENCIES_FILENAME)
        )->get();

        return new self($providers, $dependencies);
    }

    private static function getDirectory(string $dir): DirectoryInterface
    {
        return directoryForPath(
            realpath($dir)
            ?: throw new RuntimeException(
                "Unable to resolve path for `{$dir}`"
            )
        );
    }
}
