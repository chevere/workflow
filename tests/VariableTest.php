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
use Chevere\Workflow\Variable;
use PHPUnit\Framework\TestCase;

final class VariableTest extends TestCase
{
    public function testInvalidArgument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Variable('duh');
    }

    public function testConstruct(): void
    {
        $name = 'variable';
        $string = '${' . $name . '}';
        $variable = new Variable($string);
        $this->assertSame($string, $variable->__toString());
        $this->assertSame($name, $variable->name());
    }
}
