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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VariableTest extends TestCase
{
    #[DataProvider('dataProviderValidNames')]
    public function testValid(string $name): void
    {
        $variable = new Variable($name);
        $this->assertSame($name, $variable->__toString());
    }

    public static function dataProviderValidNames(): array
    {
        return [
            ['x'],
            ['xy'],
            ['abc'],
            ['abc123'],
            ['_a123'],
        ];
    }

    #[DataProvider('dataProviderInvalidNames')]
    public function testInvalid(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Variable($name);
    }

    public static function dataProviderInvalidNames(): array
    {
        return [
            [''],
            ['1'],
            ['123'],
            ['1ab'],
            ['!abc'],
            ['abc!'],
        ];
    }
}
