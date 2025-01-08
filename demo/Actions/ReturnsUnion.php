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

namespace Chevere\Demo\Actions;

use Chevere\Action\Action;
use Chevere\Parameter\Interfaces\ParameterInterface;
use function Chevere\Parameter\int;
use function Chevere\Parameter\string;
use function Chevere\Parameter\union;

final class ReturnsUnion extends Action
{
    public function __construct(
        private int|string $value
    ) {
    }

    public static function return(): ParameterInterface
    {
        return union(string(), int());
    }

    protected function main(): string|int
    {
        return $this->value;
    }
}
