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

namespace Chevere\Tests\src;

use Chevere\Parameter\Attributes\_float;
use Chevere\Parameter\Attributes\_int;
use Chevere\Parameter\Attributes\_return;
use Chevere\Workflow\Interfaces\WorkflowInterface;
use Chevere\Workflow\Interfaces\WorkflowProviderInterface;
use stdClass;
use function Chevere\Workflow\response;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;

final class TestWorkflowProviderLintMode implements WorkflowProviderInterface
{
    public static function workflow(): WorkflowInterface
    {
        return workflow(
            z: sync('not_valid_action'),
            a: sync(
                fn (#[_int(min: 200)] int $foo = 1): bool => false,
                foo: variable('wea')
            ),
            j00: sync(
                fn (stdClass $variable): stdClass => $variable,
                variable: variable('my_var')
            ),
            j0: sync(
                fn (): stdClass => new stdClass()
            )->withRunIf(variable('my_var')),
            j1: sync(
                #[_return(
                    new _int(min: 10)
                )]
                fn (): int => 100
            )->withDepends('not_found'),
            ja: sync(
                fn (
                    #[_float(min: 0.1, max: 100.20, description: 'A float variable', label: 'My Float')]
                    float $float
                ): float => $float,
                float: variable('my_float')
            ),
            j2: sync(
                fn (
                    #[_int(min: 10)]
                    int $j1
                ): int => $j1 * 2,
                j1: response('j1')
            )
                ->withRunIf(response('ja', 'key'))
                ->withRunIfNot(
                    response('j0')
                )
        );
    }
}
