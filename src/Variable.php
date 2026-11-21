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

use Chevere\Regex\Regex;
use Chevere\Workflow\Interfaces\VariableInterface;
use InvalidArgumentException;
final class Variable implements VariableInterface
{
    public function __construct(
        private string $name
    ) {
        $matches = (new Regex('/\S/'))->match($name);
        if ($matches === []) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid variable name `%s`',
                    $name
                )
            );
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
