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
    public function __construct(private string $name)
    {
        $matches = (new Regex('/^[a-zA-Z_]\w+$/'))
            ->match($name);
        if ($matches === []) {
            throw new InvalidArgumentException(
                message('Invalid variable name %name%')
                    ->withCode('%name%', $name)
            );
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
