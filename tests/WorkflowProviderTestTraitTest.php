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

namespace Chevere\Tests;

use Chevere\Tests\src\TestWorkflowProvider;
use Chevere\Workflow\Traits\WorkflowProviderTestTrait;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\run;

final class WorkflowProviderTestTraitTest extends TestCase
{
    use WorkflowProviderTestTrait;

    public function testAppNew(): void
    {
        $graph = [
            ['appAssertDomainAvailable'],
            ['user'],
            ['planRead'],
            ['currencyCode'],
            ['subCreate'],
            ['refreshUserSharedCache'],
            ['appCreate'],
            ['orderCreate'],
            ['appIdCloak'],
            ['serverAppCreateArgs'],
            ['subOrderCreate'],
            ['checkoutCreate'],
            ['appUri'],
            ['jobPushServerAppCreate'],
            ['emailAppContext'],
            ['emailAppArgs'],
            ['jobPushEmailApp'],
        ];
        $workflow = TestWorkflowProvider::workflow();
        $this->assertWorkflowGraph($graph, $workflow);
        $this->assertWorkflowGraph($graph, TestWorkflowProvider::class);
        $run = run($workflow);
        $this->assertCount(0, $run->skip());
    }
}
