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
     * @var array<class-string<WorkflowProviderInterface>>
     */
    private array $providers = [];

    /**
     * @var array<class-string>
     */
    private array $dependencies = [];

    private DirectoryInterface $directory;

    public function __construct(string $dir)
    {
        $this->directory = directoryForPath(
            realpath($dir)
            ?: throw new RuntimeException(
                "Unable to resolve path for `{$dir}`"
            )
        );
        /** @var array<class-string<WorkflowProviderInterface>> $discovered */
        $discovered = Discover::in($this->directory->path()->__toString())
            ->classes()->implementing(WorkflowProviderInterface::class)->get();
        sort($discovered);
        $this->providers = $discovered;
        $deps = [];
        foreach ($this->providers as $providerClass) {
            foreach ($providerClass::workflow()->dependencies()->classes() as $dependencyClass) {
                $deps[$dependencyClass] = true;
            }
        }
        /** @var array<class-string> $keys */
        $keys = array_keys($deps);
        sort($keys);
        $this->dependencies = $keys;
    }

    public function providers(): array
    {
        return $this->providers;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function build(?string $dir = null): void
    {
        $directory = $this->directory;
        if ($dir !== null) {
            $directory = directoryForPath(
                realpath($dir)
                ?: throw new RuntimeException(
                    "Unable to resolve path for `{$dir}`"
                )
            );
        }
        filePhpReturnForPath($directory->path()->getChild('workflow-providers.php'))
            ->put(
                new StorableVariable($this->providers)
            );
        filePhpReturnForPath($directory->path()->getChild('workflow-dependencies.php'))
            ->put(
                new StorableVariable($this->dependencies)
            );
    }
}
