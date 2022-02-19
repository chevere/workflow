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

namespace Chevere\Tests\_resources\src;

use Chevere\Workflow\Interfaces\WorkflowInterface;
use Chevere\Workflow\Interfaces\WorkflowProviderInterface;
use Chevere\Workflow\Workflow;

final class WorkflowTestProvider implements WorkflowProviderInterface
{
    public function getWorkflow(): WorkflowInterface
    {
        return new Workflow();
    }
}
