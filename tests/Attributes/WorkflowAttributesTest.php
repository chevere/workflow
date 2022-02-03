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

namespace Chevere\Tests\Workflow\Attributes;

use Chevere\Tests\Workflow\_resources\src\WorkflowTestProvider;
use Chevere\Throwable\Exceptions\InvalidArgumentException;
use Chevere\Workflow\Attributes\Provider;
use PHPUnit\Framework\TestCase;

final class WorkflowAttributesTest extends TestCase
{
    public function testWorkflowProvider(): void
    {
        $provider = WorkflowTestProvider::class;
        $attribute = new Provider($provider);
        $this->assertSame($provider, $attribute->attribute());
        $this->expectException(InvalidArgumentException::class);
        new Provider('aaaa');
    }
}
