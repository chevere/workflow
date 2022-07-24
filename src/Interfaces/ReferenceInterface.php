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

namespace Chevere\Workflow\Interfaces;

use Stringable;

/**
 * Describes the component in charge of defining a Job reference.
 */
interface ReferenceInterface extends Stringable
{
    /** ${job:key} */
    public const REGEX_REFERENCE = '/^\${([\w]*)\:([\w-]*)}$/';

    public function job(): string;

    public function parameter(): string;
}
