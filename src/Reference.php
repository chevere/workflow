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
use Chevere\Workflow\Interfaces\ReferenceInterface;

final class Reference implements ReferenceInterface
{
    private string $job;

    private string $key;

    public function __construct(private string $reference)
    {
        $regex = new Regex(self::REGEX_REFERENCE);
        $match = $regex->match($reference);
        if ($match === []) {
            throw new InvalidArgumentException(
                message('Invalid job reference %reference% (%regex%)')
                    ->withCode('%reference%', $reference)
                    ->withCode('%regex%', $regex->__toString())
            );
        }
        $this->job = strval($match[1]);
        $this->key = strval($match[2]);
    }

    public function __toString(): string
    {
        return $this->reference;
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
