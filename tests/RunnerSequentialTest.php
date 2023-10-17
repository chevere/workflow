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

use Chevere\Filesystem\File;
use Chevere\Filesystem\Interfaces\DirectoryInterface;
use Chevere\Filesystem\Interfaces\FileInterface;
use Chevere\Tests\src\TestActionFileWrite;
use PHPUnit\Framework\TestCase;
use function Chevere\Filesystem\directoryForPath;
use function Chevere\Workflow\async;
use function Chevere\Workflow\run;
use function Chevere\Workflow\workflow;

final class RunnerSequentialTest extends TestCase
{
    private DirectoryInterface $directory;

    private FileInterface $file;

    protected function setUp(): void
    {
        $this->directory = directoryForPath(__DIR__ . '/_resources');
        $this->directory->createIfNotExists();
        $this->file = new File(
            $this->directory->path()->getChild('output-sequential')
        );
    }

    protected function tearDown(): void
    {
        $this->directory->removeIfExists();
    }

    public function testSequentialRunner(): void
    {
        $this->file->removeIfExists();
        $this->file->create();
        $this->file->put('');
        $workflow = workflow(
            j1: async(
                new TestActionFileWrite(),
                file: $this->file,
            ),
            j2: async(
                new TestActionFileWrite(),
                file: $this->file,
            )->withDepends('j1'),
        );
        run($workflow);
        $this->assertStringEqualsFile(
            $this->file->path()->__toString(),
            '^$^$'
        );
        $this->file->removeIfExists();
    }
}
