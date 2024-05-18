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

namespace Chevere\Demo\Actions;

use Chevere\Action\Action;
use RuntimeException;

class FetchUrl extends Action
{
    protected function main(string $url): string
    {
        $content = file_get_contents($url);
        if ($content === false) {
            throw new RuntimeException('Error fetching URL');
        }

        return $content;
    }
}
