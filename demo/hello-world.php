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

use Chevere\Action\Action;
use Chevere\Parameter\Interfaces\ParameterInterface;
use function Chevere\Parameter\string;
use function Chevere\Workflow\run;
use function Chevere\Workflow\sync;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;

require 'loader.php';

class GreetAction extends Action
{
    public static function return(): ParameterInterface
    {
        return string('/^Hello, /');
    }

    protected function main(string $username): string
    {
        return "Hello, {$username}!";
    }
}

$workflow = workflow(
    greet: sync(
        new GreetAction(),
        username: variable('username'),
    ),
);
$run = run(
    $workflow,
    username: $argv[1] ?? 'Walala'
);
echo $run->getReturn('greet')->string(); // Hello, Walala!
echo PHP_EOL;
