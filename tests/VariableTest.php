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

use Chevere\Workflow\Variable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VariableTest extends TestCase
{
    public function testInvalidArgumentEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Variable('');
    }

    public function testInvalidArgumentNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Variable('123');
    }

    public function testInvalidArgumentStartsWithNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Variable('1ab');
    }

    public function testConstruct(): void
    {
        $names = ['abc', 'abc123', '_a123'];
        foreach ($names as $name) {
            $variable = new Variable($name);
            $this->assertSame($name, $variable->__toString());
        }
    }
}
