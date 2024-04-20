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

use Chevere\Workflow\ResponseReference;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResponseReferenceTest extends TestCase
{
    public function testInvalidArgumentEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(100);
        new ResponseReference('', '');
    }

    public function testInvalidArgumentSpaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(100);
        new ResponseReference(' ', ' ');
    }

    public function testInvalidArgumentKeyEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(101);
        new ResponseReference('job', '');
    }

    public function testInvalidArgumentKeySpaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(101);
        new ResponseReference('job', ' ');
    }

    public function testConstruct(): void
    {
        $job = 'job';
        $key = 'key';
        $string = $job . ':' . $key;
        $reference = new ResponseReference($job, $key);
        $this->assertSame($string, $reference->__toString());
        $this->assertSame($job, $reference->job());
        $this->assertSame($key, $reference->key());
    }

    public function testConstructNoKey(): void
    {
        $job = 'job';
        $key = null;
        $string = $job;
        $reference = new ResponseReference($job, $key);
        $this->assertSame($string, $reference->__toString());
        $this->assertSame($job, $reference->job());
        $this->assertSame($key, $reference->key());
    }
}
