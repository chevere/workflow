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
use Chevere\Workflow\Variable;
use PHPUnit\Framework\TestCase;

final class VariableTest extends TestCase
{
    public function testInvalidArgumentEmpty(): void
    {
        $this->expectException(StrEmptyException::class);
        new Variable('');
    }

    public function testInvalidArgumentSpaces(): void
    {
        $this->expectException(StrCtypeSpaceException::class);
        new Variable(' ');
    }

    public function testConstruct(): void
    {
        $name = 'variable';
        $string = '${' . $name . '}';
        $variable = new Variable($name);
        $this->assertSame($string, $variable->__toString());
        $this->assertSame($name, $variable->name());
    }
}
