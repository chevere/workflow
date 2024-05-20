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

use Chevere\Demo\Actions\FetchUrl;
use function Chevere\Workflow\async;
use function Chevere\Workflow\run;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;

require 'loader.php';

/*
php demo/sync-vs-async.php
*/
$sync = workflow(
    php: sync(
        new FetchUrl(),
        url: variable('php'),
    ),
    github: sync(
        new FetchUrl(),
        url: variable('github'),
    ),
    chevere: sync(
        new FetchUrl(),
        url: variable('chevere'),
    ),
);
$async = workflow(
    php: async(
        new FetchUrl(),
        url: variable('php'),
    ),
    github: async(
        new FetchUrl(),
        url: variable('github'),
    ),
    chevere: async(
        new FetchUrl(),
        url: variable('chevere'),
    ),
);
$variables = [
    'php' => 'https://www.php.net',
    'github' => 'https://github.com/chevere/workflow',
    'chevere' => 'https://chevere.org',
];
$time = microtime(true);
$run = run($sync, ...$variables);
$time = microtime(true) - $time;
echo "Time sync: {$time}\n";
$time = microtime(true);
$run = run($async, ...$variables);
$time = microtime(true) - $time;
echo "Time async: {$time}\n";
