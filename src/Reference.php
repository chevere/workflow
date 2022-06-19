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

use Chevere\Str\StrAssert;
use Chevere\Workflow\Interfaces\ReferenceInterface;

final class Reference implements ReferenceInterface
{
    public function __construct(private string $job, private string $key)
    {
        (new StrAssert($job))->notCtypeSpace()->notEmpty();
        (new StrAssert($key))->notCtypeSpace()->notEmpty();
    }

    public function __toString(): string
    {
        return '${' . $this->job . ':' . $this->key . '}';
    }

    public function job(): string
    {
        return $this->job;
    }

    public function key(): string
    {
        return $this->key;
    }
}
