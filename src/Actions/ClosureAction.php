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

namespace Chevere\Workflow\Actions;

use Chevere\Action\Action;
use Chevere\Parameter\Interfaces\ParameterInterface;
use Closure;
use function Chevere\Parameter\null;

final class ClosureAction extends Action
{
    public function __construct(
        private Closure $closure,
    ) {
    }

    public static function acceptResponse(): ParameterInterface
    {
        return null();
    }

    protected function run(): void
    {
        ($this->closure)(...func_get_args());
    }
}
