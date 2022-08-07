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

use Chevere\String\Exceptions\CtypeSpaceException;
use Chevere\String\Exceptions\EmptyException;
use Chevere\Workflow\Reference;
use PHPUnit\Framework\TestCase;

final class ReferenceTest extends TestCase
{
    public function testInvalidArgumentEmpty(): void
    {
        $this->expectException(EmptyException::class);
        new Reference('', '');
    }

    public function testInvalidArgumentSpaces(): void
    {
        $this->expectException(CtypeSpaceException::class);
        new Reference(' ', ' ');
    }

    public function testConstruct(): void
    {
        $job = 'job';
        $key = 'key';
        $string = '${' . $job . ':' . $key . '}';
        $reference = new Reference($job, $key);
        $this->assertSame($string, $reference->__toString());
        $this->assertSame($job, $reference->job());
        $this->assertSame($key, $reference->parameter());
    }
}
