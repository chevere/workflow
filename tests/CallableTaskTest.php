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

use Chevere\Workflow\CallableTask;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CallableTaskTest extends TestCase
{
    public function testConstruct(): void
    {
        $callable = 'is_string';
        $task = new CallableTask($callable, 'string');
        $channel = $this->createMock('Amp\Sync\Channel');
        $cancellation = $this->createMock('Amp\Cancellation');
        $return = $task->run($channel, $cancellation);
        $this->assertTrue($return);
        $task = new CallableTask($callable, 1);
        $return = $task->run($channel, $cancellation);
        $this->assertFalse($return);
    }

    public function testInvalidCallable(): void
    {
        $callable = function () {
            return 'string';
        };
        $arguments = [];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument $callable must be a callable string');
        new CallableTask($callable, ...$arguments);
    }
}
