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
use Chevere\Workflow\Variable;
use PHPUnit\Framework\TestCase;

final class VariableTest extends TestCase
{
    public function testInvalidArgumentEmpty(): void
    {
        $this->expectException(EmptyException::class);
        new Variable('');
    }

    public function testInvalidArgumentSpaces(): void
    {
        $this->expectException(CtypeSpaceException::class);
        new Variable(' ');
    }

    public function testConstruct(): void
    {
        $name = 'variable';
        $variable = new Variable($name);
        $this->assertSame($name, $variable->__toString());
    }
}
