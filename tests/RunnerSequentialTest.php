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

use function Chevere\Filesystem\directoryForPath;
use Chevere\Filesystem\File;
use Chevere\Filesystem\Interfaces\DirectoryInterface;
use Chevere\Tests\_resources\src\TestActionFileWrite;
use function Chevere\Workflow\async;
use function Chevere\Workflow\run;
use function Chevere\Workflow\workflow;
use PHPUnit\Framework\TestCase;

final class RunnerSequentialTest extends TestCase
{
    private DirectoryInterface $directory;

    protected function setUp(): void
    {
        $this->directory = directoryForPath(__DIR__ . '/_resources/temp');
        $this->directory->createIfNotExists();
    }

    protected function tearDown(): void
    {
        $this->directory->removeIfExists();
    }

    public function testSequentialRunner(): void
    {
        $file = new File($this->directory->path()->getChild('output-sequential'));
        $file->removeIfExists();
        $file->create();
        $file->put('');
        $action = new TestActionFileWrite();
        $workflow = workflow(
            j1: async(
                TestActionFileWrite::class,
                file: $file,
            ),
            j2: async(
                TestActionFileWrite::class,
                file: $file,
            )->withDepends('j1'),
        );
        $arguments = [];
        run($workflow, ...$arguments);
        $this->assertStringEqualsFile(
            $file->path()->__toString(),
            str_repeat('^$', 2)
        );
        $file->removeIfExists();
    }
}
