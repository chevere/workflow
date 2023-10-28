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

use Chevere\Demo\ImageResize;
use Chevere\Demo\StoreFile;
use function Chevere\Workflow\async;
use function Chevere\Workflow\response;
use function Chevere\Workflow\run;
use function Chevere\Workflow\variable;
use function Chevere\Workflow\workflow;

foreach (['/../', '/../../../../'] as $path) {
    $autoload = __DIR__ . $path . 'vendor/autoload.php';
    if (stream_resolve_include_path($autoload)) {
        require $autoload;

        break;
    }
}

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
$variables = [
    'image' => '/path/to/image-to-upload.png',
    'savePath' => '/path/to/storage/',
];
$run = run($workflow, $variables);
echo <<<PLAIN
thumbFile: {$run->getResponse('thumb')->string()}
posterFile: {$run->getResponse('poster')->string()}

PLAIN;
