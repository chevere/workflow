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

use Chevere\Demo\Actions\ReturnsUnion;
use function Chevere\Workflow\run;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\workflow;

require 'loader.php';

/*
php demo/union.php
*/

$workflow = workflow(
    job1: sync(
        new ReturnsUnion(123),
    ),
);
$run = run($workflow);
$unionResponse = $run->response('job1')->int();
var_dump($unionResponse);
