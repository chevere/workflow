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

use Chevere\Parameter\Attributes\_arrayp;
use Chevere\Parameter\Attributes\_return;
use Chevere\Parameter\Attributes\_string;
use PHPUnit\Framework\TestCase;
use function Chevere\Workflow\response;
use function Chevere\Workflow\run;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\workflow;

final class WorkflowComplexTest extends TestCase
{
    public function testAppNew(): void
    {
        $workflow = workflow(
            appAssertDomainAvailable: sync(
                fn () => null,
            ),
            user: sync(
                #[_return(
                    new _arrayp(
                        name: new _string(),
                        email: new _string()
                    )
                )]
                fn (): array => [
                    'name' => 'User Name',
                    'email' => 'user@example.com',
                ],
            ),
            plan: sync(
                #[_return(
                    new _arrayp(
                        currency_code: new _string()
                    )
                )]
                fn (): array => [
                    'currency_code' => 'usd',
                ],
            ),
            subCreate: sync(
                fn (string $currency_code): int => 1,
                currency_code: response('plan', 'currency_code')
            ),
            refreshUserSharedCache: sync(
                fn () => null,
            ),
            appCreate: sync(
                fn (int $sub_id): int => 1,
                sub_id: response('subCreate'),
            ),
            orderCreate: sync(
                fn (int $product_id): int => 1,
                product_id: response('appCreate'),
            ),
            subOrderCreate: sync(
                fn (int $sub_id, int $order_id): int => 1,
                order_id: response('orderCreate'),
                sub_id: response('subCreate'),
            ),
            checkoutCreate: sync(
                fn (int $order_id, int $product_id): int => 1,
                order_id: response('orderCreate'),
                product_id: response('appCreate')
            ),
            appIdCloak: sync(
                fn (int $id): string => 'cloak' . $id,
                id: response('appCreate'),
            ),
            appUri: sync(
                fn (string ...$value): string => '',
                idCloak: response('appIdCloak'),
            ),
            serverAppCreateArgs: sync(
                function (
                    int $id,
                ): array {
                    return get_defined_vars();
                },
                id: response('appCreate')
            ),
            jobPushServerAppCreate: sync(
                fn (
                    mixed $arguments = [],
                ) => null,
                arguments: response('serverAppCreateArgs'),
            ),
            emailAppContext: sync(
                fn (string $uri, string $idCloak): array => [],
                uri: response('appUri'),
                idCloak: response('appIdCloak'),
            ),
            emailAppArgs: sync(
                function (
                    string $toEmail,
                    string $toName,
                    array $context
                ): array {
                    return get_defined_vars();
                },
                toEmail: response('user', 'email'),
                toName: response('user', 'name'),
                context: response('emailAppContext'),
            ),
            jobPushEmailApp: sync(
                fn (
                    mixed $arguments = [],
                ) => null,
                arguments: response('emailAppArgs'),
            ),
        );
        $this->assertSame(
            [
                ['appAssertDomainAvailable'],
                ['user'],
                ['plan'],
                ['refreshUserSharedCache'],
                ['subCreate'],
                ['appCreate'],
                ['orderCreate'],
                ['appIdCloak'],
                ['serverAppCreateArgs'],
                ['appUri'],
                ['jobPushServerAppCreate'],
                ['subOrderCreate'],
                ['checkoutCreate'],
                ['emailAppContext'],
                ['emailAppArgs'],
                ['jobPushEmailApp'],
            ],
            $workflow->jobs()->graph()->toArray()
        );
        $run = run($workflow);
        $this->assertCount(0, $run->skip());
    }
}
