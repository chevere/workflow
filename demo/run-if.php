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

use Chevere\Demo\Actions\Greet;
use function Chevere\Workflow\run;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;

require 'loader.php';

/*
php demo/run-if.php Rodolfo
php demo/run-if.php
*/

$workflow = workflow(
    greet: sync(
        new Greet(),
        username: variable('username'),
    )->withRunIf(
        variable('sayHello')
    ),
);
$name = $argv[1] ?? '';
$run = run(
    $workflow,
    username: $name,
    sayHello: $name !== ''
);
if ($run->skip()->contains('greet')) {
    exit;
}
$greet = $run->getReturn('greet')->string();
echo <<<PLAIN
{$greet}

PLAIN;
