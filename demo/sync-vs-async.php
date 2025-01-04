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
    job1: sync(
        new FetchUrl(),
        url: variable('php'),
    ),
    job2: sync(
        new FetchUrl(),
        url: variable('github'),
    ),
    job3: sync(
        new FetchUrl(),
        url: variable('chevere'),
    ),
);
$async = workflow(
    job1: async(
        new FetchUrl(),
        url: variable('php'),
    ),
    job2: async(
        new FetchUrl(),
        url: variable('github'),
    ),
    job3: async(
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
$time = round((microtime(true) - $time) * 1000);
echo "Time (ms)  sync: {$time}\n";

$time = microtime(true);
$run = run($async, ...$variables);
$time = round((microtime(true) - $time) * 1000);
echo "Time (ms) async: {$time}\n";
