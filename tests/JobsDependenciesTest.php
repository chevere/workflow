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

use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Throwable\Exceptions\OverflowException;
use Chevere\Workflow\JobsDependencies;
use PHPUnit\Framework\TestCase;

final class JobsDependenciesTest extends TestCase
{
    public function testWithPut(): void
    {
        $dependencies = new JobsDependencies();
        $expected = [];
        $this->assertSame($expected, $dependencies->getGraph());
        $dependencies = $dependencies->withPut('j0', 'j1');
        $expected = [
            0 => ['j1'],
            1 => ['j0'],
        ];
        $this->assertSame($expected, $dependencies->getGraph());
        $dependencies = $dependencies->withPut('j0', 'j1');
        $this->assertSame($expected, $dependencies->getGraph());
        $dependencies = $dependencies->withPut('j0', 'j2');
        $expected = [
            0 => ['j1', 'j2'],
            1 => ['j0'],
        ];
        $this->assertSame($expected, $dependencies->getGraph());
        $dependencies = $dependencies->withPut('j1');
        $dependencies = $dependencies->withPut('j2');
        $expected = [
            0 => ['j1', 'j2'],
            1 => ['j0']
        ];
        $this->assertSame($expected, $dependencies->getGraph());
        $dependencies = $dependencies->withPut('j2', 'j0');
        $expected = [
            0 => ['j1'],
            1 => ['j0'],
            2 => ['j2']
        ];
        $this->assertSame($expected, $dependencies->getGraph());
        $dependencies = $dependencies->withPut('j1', 'j0');
        $expected = [
            0 => ['j0'],
            1 => ['j1', 'j2'],
        ];
        $this->assertSame($expected, $dependencies->getGraph());
        $dependencies = $dependencies->withPut('j0', 'j1');
        $expected = [
            0 => ['j1'],
            1 => ['j0'],
            2 => ['j2'],
        ];
        $this->assertSame($expected, $dependencies->getGraph());
        $dependencies = $dependencies->withPut('j0', 'j2');
        $expected = [
            0 => ['j1', 'j2'],
            1 => ['j0'],
        ];
        $this->assertSame($expected, $dependencies->getGraph());
    }

    public function testWithPutSelf(): void
    {
        $dependencies = new JobsDependencies();
        $this->expectException(InvalidArgumentException::class);
        $dependencies->withPut('j0', 'j0');
    }

    public function testWithPutDupes(): void
    {
        $dependencies = new JobsDependencies();
        $this->expectException(OverflowException::class);
        $dependencies->withPut('j0', 'j1', 'j1');
    }
}
