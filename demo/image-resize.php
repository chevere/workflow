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

use Chevere\Demo\Actions\ImageResize;
use Chevere\Demo\Actions\StoreFile;
use function Chevere\Workflow\async;
use function Chevere\Workflow\response;
use function Chevere\Workflow\run;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;

require 'loader.php';

/*
Run the following command in your terminal:
php demo/image-resize.php
*/

$workflow = workflow(
    thumb: async(
        new ImageResize(),
        file: variable('image'),
        fit: 'thumbnail',
    ),
    poster: async(
        new ImageResize(),
        file: variable('image'),
        fit: 'poster',
    ),
    storeThumb: async(
        new StoreFile(),
        file: response('thumb'),
        path: variable('savePath'),
    ),
    storePoster: async(
        new StoreFile(),
        file: response('poster'),
        path: variable('savePath'),
    )
);
$run = run(
    $workflow,
    image: '/path/to/image-to-upload.png',
    savePath: '/path/to/storage/',
);
$graph = $run->workflow()->jobs()->graph()->toArray();
echo "Workflow graph:\n";
foreach ($graph as $level => $jobs) {
    echo " - {$level}: [" . implode('||', $jobs) . "]\n";
}
echo <<<PLAIN
thumbFile: {$run->getReturn('thumb')->string()}
posterFile: {$run->getReturn('poster')->string()}

PLAIN;
