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

namespace Chevere\Workflow;

use function Chevere\Message\message;
use Chevere\Regex\Regex;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Workflow\Interfaces\VariableInterface;

final class Variable implements VariableInterface
{
    private string $name;

    public function __construct(private string $variable)
    {
        $regex = new Regex(self::REGEX_VARIABLE);
        $match = $regex->match($variable);
        if ($match === []) {
            throw new InvalidArgumentException(
                message('Invalid Workflow variable %variable% (%regex%)')
                    ->withCode('%variable%', $variable)
                    ->withCode('%regex%', $regex->__toString())
            );
        }
        $this->name = $match[1];
    }

    public function __toString(): string
    {
        return $this->variable;
    }

    public function name(): string
    {
        return $this->name;
    }
}
