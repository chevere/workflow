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

use function Chevere\Workflow\{response, run, sync, variable, workflow};

require 'loader.php';

$workflow = workflow(
    calculate: sync(
        function (int $a, int $b): int {
            return $a + $b;
        },
        a: 10,
        b: variable('value')
    ),
    format: sync(
        fn (int $result): string => "Result: {$result}",
        result: response('calculate')
    )
);

$run = run($workflow, value: 5);
// Result: 15
echo $run->response('format')->string() . PHP_EOL;
