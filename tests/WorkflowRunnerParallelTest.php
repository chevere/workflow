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
use Chevere\Tests\_resources\src\TestActionWrite;
use function Chevere\Workflow\job;
use function Chevere\Workflow\workflow;
use function Chevere\Workflow\workflowRun;
use PHPUnit\Framework\TestCase;

final class WorkflowRunnerParallelTest extends TestCase
{
    public function testParallelRunner(): void
    {
        $file = fileForPath(__DIR__ . '/_resources/output-parallel');
        $file->removeIfExists();
        $file->create();
        $file->put('');
        $action = new TestActionWrite();
        $workflow = workflow(
            j1: job(
                TestActionWrite::class,
                file: $file,
            ),
            j2: job(
                TestActionWrite::class,
                file: $file,
            ),
        );
        $arguments = [];
        $run = workflowRun($workflow, ...$arguments);
        $this->assertStringEqualsFile(
            $file->path()->__toString(),
            '^^$$'
        );
        $file->removeIfExists();
    }
}
