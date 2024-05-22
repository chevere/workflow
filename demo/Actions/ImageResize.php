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
use Chevere\Parameter\Attributes\StringAttr;
use Chevere\Parameter\Interfaces\StringParameterInterface;
use RuntimeException;
use function Chevere\Parameter\string;

class ImageResize extends Action
{
    public const FIT_WIDTH = [
        'thumbnail' => 50,
        'poster' => 150,
    ];

    public static function return(): StringParameterInterface
    {
        return string();
    }

    protected function main(
        #[StringAttr('/\.jpe?g$/')]
        string $file,
        string $fit
    ): string {
        [$width, $height] = getimagesize($file);
        $targetWidth = self::FIT_WIDTH[$fit];
        $targetHeight = intval($height / $width * $targetWidth);
        $image = imagecreatetruecolor($targetWidth, $targetHeight);
        $source = imagecreatefromjpeg($file);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $pos = strrpos($file, '.');
        $target = substr($file, 0, $pos)
            . ".{$fit}"
            . substr($file, $pos);

        return imagejpeg($image, $target)
            ? $target
            : throw new RuntimeException('Unable to save image');
    }
}
