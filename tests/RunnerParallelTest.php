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

use Chevere\Tests\src\TestActionFileWrite;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\async;
use function Chevere\Workflow\run;
use function Chevere\Workflow\workflow;

final class RunnerParallelTest extends TestCase
{
    private string $directory;

    private string $file;

    protected function setUp(): void
    {
        $this->directory = __DIR__ . '/_resources/';
        if (! is_dir($this->directory)) {
            mkdir($this->directory);
        }
        $this->file = $this->directory . 'output-parallel';
        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->file)) {
            unlink($this->file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testParallelRunner(): void
    {
        if (file_put_contents($this->file, '') === false) {
            $this->markTestIncomplete('Unable to write to file');
        }
        $workflow = workflow(
            j1: async(
                new TestActionFileWrite(),
                file: $this->file,
            ),
            j2: async(
                new TestActionFileWrite(),
                file: $this->file,
            ),
        );
        run($workflow);
        $this->assertStringEqualsFile(
            $this->file,
            '^^$$'
        );
    }
}
