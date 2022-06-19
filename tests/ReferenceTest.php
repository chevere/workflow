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

use Chevere\Str\Exceptions\StrCtypeSpaceException;
use Chevere\Str\Exceptions\StrEmptyException;
use Chevere\Workflow\Reference;
use PHPUnit\Framework\TestCase;

final class ReferenceTest extends TestCase
{
    public function testInvalidArgumentEmpty(): void
    {
        $this->expectException(StrEmptyException::class);
        new Reference('', '');
    }

    public function testInvalidArgumentSpaces(): void
    {
        $this->expectException(StrCtypeSpaceException::class);
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
        $this->assertSame($key, $reference->key());
    }
}
