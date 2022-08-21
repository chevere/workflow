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

use Chevere\String\AssertString;
use Chevere\Workflow\Interfaces\ReferenceInterface;

final class Reference implements ReferenceInterface
{
    /**
     * @param string $job Job name
     * @param string $parameter Response parameter name
     */
    public function __construct(private string $job, private string $parameter)
    {
        (new AssertString($job))->notCtypeSpace()->notEmpty();
        (new AssertString($parameter))->notCtypeSpace()->notEmpty();
    }

    public function __toString(): string
    {
        return $this->job . ':' . $this->parameter;
    }

    public function job(): string
    {
        return $this->job;
    }

    public function parameter(): string
    {
        return $this->parameter;
    }
}
