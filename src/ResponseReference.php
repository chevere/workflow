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

use Chevere\String\StringAssert;
use Chevere\Workflow\Interfaces\ResponseReferenceInterface;

final class ResponseReference implements ResponseReferenceInterface
{
    public function __construct(
        private string $job,
        private ?string $key,
    ) {
        (new StringAssert($job))->notCtypeSpace()->notEmpty();
        if ($key === null) {
            return;
        }
        (new StringAssert($key))->notCtypeSpace();
    }

    public function __toString(): string
    {
        return match ($this->key) {
            null => $this->job,
            default => "{$this->job}:{$this->key}",
        };
    }

    public function job(): string
    {
        return $this->job;
    }

    public function key(): ?string
    {
        return $this->key;
    }
}
