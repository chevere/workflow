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

final class Config
{
    public function __construct(
        public readonly bool $isLint = false,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            isLint: getenv('CHEVERE_WORKFLOW_LINT_ENABLE') === '1'
        );
    }
}
