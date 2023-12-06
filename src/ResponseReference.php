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

use Chevere\Workflow\Interfaces\ResponseReferenceInterface;
use InvalidArgumentException;

final class ResponseReference implements ResponseReferenceInterface
{
    public function __construct(
        private string $job,
        private ?string $key,
    ) {
        if (ctype_space($job) || empty($job)) {
            throw new InvalidArgumentException();
        }
        if ($key === null) {
            return;
        }
        if (ctype_space($key)) {
            throw new InvalidArgumentException();
        }
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
