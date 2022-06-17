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
use Chevere\Workflow\Reference;
use PHPUnit\Framework\TestCase;

final class ReferenceTest extends TestCase
{
    public function testInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Reference('duh');
    }

    public function testConstruct(): void
    {
        $job = 'job';
        $key = 'key';
        $string = '${' . $job . ':' . $key . '}';
        $reference = new Reference($string);
        $this->assertSame($string, $reference->__toString());
        $this->assertSame($job, $reference->job());
        $this->assertSame($key, $reference->key());
    }
}
