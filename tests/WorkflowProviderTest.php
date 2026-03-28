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
use PHPUnit\Framework\TestCase;

final class WorkflowProviderTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('CHEVERE_WORKFLOW_LINT_ENABLE=1');
    }

    protected function tearDown(): void
    {
        putenv('CHEVERE_WORKFLOW_LINT_ENABLE');
    }

    public function testLint(): void
    {
        $lint = json_decode(TestWorkflowProvider::workflow()->lint(), true);
        $this->assertSame(
            [
                [
                    'job' => 'j0',
                    'method' => 'withRunIf',
                    'variable' => 'my_var',
                    'message' => 'Variable **my_var** (previously inferred as `className`) is not of type `bool|int` at Job **j0**',
                ],
                [
                    'job' => 'j1',
                    'message' => 'Job **j1** has undeclared dependencies: `not_found`',
                ],
                [
                    'job' => 'j2',
                    'method' => 'withRunIf',
                    'response' => 'ja->key',
                    'message' => "Response **ja->key** job `ja` doesn't bind to `key` parameter",
                ],
                [
                    'job' => 'j2',
                    'method' => 'withRunIfNot',
                    'response' => 'j0',
                    'message' => 'Response **j0** must be of type `bool|int`, type `className` provided',
                ],
            ],
            $lint['violations']
        );
        $this->assertSame(
            <<<MERMAID
            graph TB;
                j00("`j00`");
                j0("`j0
            *if* var(my_var)`");
                ja("`ja`");
                j1("`j1`");
                j2("`j2
            *if* res(ja->key)
            *ifNot* res(j0)`");

                not_found-->j1;
                j1-->|"j1 @ j2(j1:)"|j2;
                ja-->j2;
                j0-->j2;

            MERMAID,
            $lint['mermaid']
        );
        $this->assertSame(
            [
                // NOTE: Default null doesn't mean nullable. Union types denote nullability (when `null` is included in the union).
                // Default is the value on the signature `$wea = 124` if any
                'my_var' => [
                    'required' => true,
                    'type' => 'className',
                    'className' => 'stdClass',
                    'description' => '',
                    'default' => null,
                ],
                'my_float' => [
                    'required' => true,
                    'type' => 'float',
                    'description' => 'A float variable',
                    'default' => null,
                    'min' => 0.1,
                    'max' => 100.2,
                    'accept' => [],
                    'reject' => [],
                ],
            ],
            $lint['variables'],
        );
    }
}
