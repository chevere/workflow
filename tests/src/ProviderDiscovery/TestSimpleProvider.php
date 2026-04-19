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

namespace Chevere\Tests\src\ProviderDiscovery;

use Chevere\Workflow\Interfaces\WorkflowInterface;
use Chevere\Workflow\Interfaces\WorkflowProviderInterface;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\workflow;

final class TestSimpleProvider implements WorkflowProviderInterface
{
    public static function workflow(): WorkflowInterface
    {
        return workflow(
            step: sync(fn (): string => 'done'),
        );
    }
}
