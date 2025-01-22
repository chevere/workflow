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

use Chevere\Workflow\Interfaces\CallerInterface;

final class Caller implements CallerInterface
{
    public function __construct(
        private string $file,
        private int $line
    ) {
    }

    /**
     * @return string The file and line number in the format `file:line`
     */
    public function __toString(): string
    {
        return $this->file . ':' . $this->line;
    }

    public function file(): string
    {
        return $this->file;
    }

    public function line(): int
    {
        return $this->line;
    }
}
