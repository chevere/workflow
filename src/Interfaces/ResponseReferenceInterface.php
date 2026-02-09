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
 * Describes a response reference that can target either a complete job response
 * or a specific key within that response.
 */
interface ResponseReferenceInterface extends Stringable
{
    /**
     * Provides the name/identifier of the job whose response is being referenced.
     *
     * @return string The job identifier
     */
    public function job(): string;

    /**
     * Provides the optional key to access a specific field within the job's response.
     *
     * When null, the entire response from the job is referenced.
     * When provided, only the specified field/property of the response is referenced.
     *
     * @return string|null The response key, or null if referencing the entire response
     */
    public function key(): ?string;
}
