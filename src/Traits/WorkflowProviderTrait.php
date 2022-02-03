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

use Chevere\Workflow\Interfaces\WorkflowInterface;

/**
 * @codeCoverageIgnore
 */
trait WorkflowProviderTrait
{
    abstract public function getWorkflow(): WorkflowInterface;
}
