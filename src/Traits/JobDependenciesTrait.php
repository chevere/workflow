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

namespace Chevere\Workflow\Traits;

use function Chevere\Message\message;
use Chevere\Str\StrAssert;
use Chevere\Throwable\Exceptions\OverflowException;

/**
 * @codeCoverageIgnore
 */
trait JobDependenciesTrait
{
    private function assertDependencies(string ...$dependencies): void
    {
        $uniques = array_unique($dependencies);
        if ($uniques !== $dependencies) {
            throw new OverflowException(
                message('Job dependencies must be unique (repeated %dependencies%)')
                    ->code(
                        '%dependencies%',
                        implode(', ', array_diff_assoc($dependencies, $uniques))
                    )
            );
        }
        foreach ($dependencies as $dependency) {
            (new StrAssert($dependency))
                ->notEmpty()
                ->notCtypeDigit()
                ->notCtypeSpace();
        }
    }
}
