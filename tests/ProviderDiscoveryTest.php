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

namespace Chevere\Tests;

use Chevere\Tests\src\ProviderDiscovery\TestProviderWithDependency;
use Chevere\Tests\src\ProviderDiscovery\TestSimpleProvider;
use Chevere\Tests\src\TestActionRequiresInterface;
use Chevere\Workflow\ProviderDiscovery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function Chevere\Filesystem\directoryForPath;
use function Chevere\Filesystem\filePhpReturnForPath;

final class ProviderDiscoveryTest extends TestCase
{
    private string $fixtureDir;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->fixtureDir = realpath(__DIR__ . '/src/ProviderDiscovery');
        $this->tempDir = sys_get_temp_dir() . '/chevere-workflow-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        directoryForPath($this->tempDir)->removeIfExists();
    }

    public function testInvalidPath(): void
    {
        $this->expectException(RuntimeException::class);
        new ProviderDiscovery('/non/existent/path/that/does/not/exist');
    }

    public function testNoProviders(): void
    {
        $discovery = new ProviderDiscovery($this->tempDir);
        $this->assertSame([], $discovery->providers());
        $this->assertSame([], $discovery->dependencies());
    }

    public function testProviders(): void
    {
        $discovery = new ProviderDiscovery($this->fixtureDir);
        $this->assertSame(
            [
                TestProviderWithDependency::class,
                TestSimpleProvider::class,
            ],
            $discovery->providers()
        );
    }

    public function testProvidersAreSorted(): void
    {
        $discovery = new ProviderDiscovery($this->fixtureDir);
        $providers = $discovery->providers();
        $sorted = $providers;
        sort($sorted);
        $this->assertSame($sorted, $providers);
    }

    public function testDependencies(): void
    {
        $discovery = new ProviderDiscovery($this->fixtureDir);
        $this->assertSame(
            [TestActionRequiresInterface::class],
            $discovery->dependencies()
        );
    }

    public function testDependenciesAreSorted(): void
    {
        $discovery = new ProviderDiscovery($this->fixtureDir);
        $dependencies = $discovery->dependencies();
        $sorted = $dependencies;
        sort($sorted);
        $this->assertSame($sorted, $dependencies);
    }

    public function testBuildDefaultDir(): void
    {
        $discovery = new ProviderDiscovery($this->tempDir);
        $discovery->build();
        $dir = directoryForPath($this->tempDir);
        $providers = filePhpReturnForPath($dir->path()->getChild('workflow-providers.php'))->get();
        $this->assertSame([], $providers);
        $dependencies = filePhpReturnForPath($dir->path()->getChild('workflow-dependencies.php'))->get();
        $this->assertSame([], $dependencies);
    }

    public function testBuildExplicitDir(): void
    {
        $discovery = new ProviderDiscovery($this->fixtureDir);
        $discovery->build($this->tempDir);
        $dir = directoryForPath($this->tempDir);
        $providers = filePhpReturnForPath($dir->path()->getChild('workflow-providers.php'))->get();
        $this->assertSame($discovery->providers(), $providers);
        $dependencies = filePhpReturnForPath($dir->path()->getChild('workflow-dependencies.php'))->get();
        $this->assertSame($discovery->dependencies(), $dependencies);
    }

    public function testBuildInvalidDir(): void
    {
        $discovery = new ProviderDiscovery($this->tempDir);
        $this->expectException(RuntimeException::class);
        $discovery->build('/non/existent/build/path');
    }
}
