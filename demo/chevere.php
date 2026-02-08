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

use Chevere\Demo\Actions\MyAction;
use function Chevere\Workflow\{response,run,sync,variable,workflow};

require 'loader.php';

/*
 * php demo/chevere.php
 */

$workflow = workflow(
    greet: sync(
        MyAction::class,
        foo: variable('super'),
    ),
    capo: sync(
        MyAction::class,
        foo: response('greet'),
    ),
    wea: sync(
        function (string $foo) {
            return "Wea, {$foo}";
        },
        foo: response('greet'),
    ),
);
$hello = run(
    $workflow,
    super: 'Chevere',
);
echo $hello->response('greet')->string() . PHP_EOL;
// Hello, Chevere
echo $hello->response('capo')->string() . PHP_EOL;
// Hello, Hello, Chevere
echo $hello->response('wea')->string() . PHP_EOL;
// Wea, Hello, Chevere
