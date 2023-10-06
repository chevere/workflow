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
use function Chevere\Filesystem\fileForPath;
use function Chevere\Workflow\async;
use function Chevere\Workflow\run;
use function Chevere\Workflow\workflow;

final class RunnerParallelTest extends TestCase
{
    public function testParallelRunner(): void
    {
        $file = fileForPath(__DIR__ . '/_resources/output-parallel');
        $file->removeIfExists();
        $file->create();
        $file->put('');
        $workflow = workflow(
            j1: async(
                TestActionFileWrite::class,
                file: $file,
            ),
            j2: async(
                TestActionFileWrite::class,
                file: $file,
            ),
        );
        run($workflow);
        $this->assertStringEqualsFile(
            $file->path()->__toString(),
            '^^$$'
        );
        $file->removeIfExists();
    }
}
