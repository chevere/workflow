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

use Chevere\String\AssertString;
use Chevere\Workflow\Interfaces\VariableInterface;

final class Variable implements VariableInterface
{
    public function __construct(private string $name)
    {
        (new AssertString($name))->notCtypeSpace()->notEmpty();
    }

    public function __toString(): string
    {
        return '${' . $this->name . '}';
    }

    public function name(): string
    {
        return $this->name;
    }
}
