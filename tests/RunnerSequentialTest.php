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

use function Chevere\Filesystem\fileForPath;
use Chevere\Tests\_resources\src\TestActionFileWrite;
use function Chevere\Workflow\job;
use function Chevere\Workflow\workflow;
use function Chevere\Workflow\workflowRun;
use PHPUnit\Framework\TestCase;

final class RunnerSequentialTest extends TestCase
{
    public function testSequentialRunner(): void
    {
        $file = fileForPath(__DIR__ . '/_resources/output-sequential');
        $file->removeIfExists();
        $file->create();
        $file->put('');
        $action = new TestActionFileWrite();
        $workflow = workflow(
            j1: job(
                TestActionFileWrite::class,
                file: $file,
            ),
            j2: job(
                TestActionFileWrite::class,
                file: $file,
            )->withDepends('j1'),
        );
        $arguments = [];
        $run = workflowRun($workflow, ...$arguments);
        $this->assertStringEqualsFile(
            $file->path()->__toString(),
            str_repeat('^$', 2)
        );
        $file->removeIfExists();
    }
}
