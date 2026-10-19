<?php

declare(strict_types=1);

namespace Modules\Core\Media;

use InvalidArgumentException;

/**
 * Re-encode an uploaded picture as a small WebP (S24): scaled to a maximum width, compressed, and stripped of all
 * metadata (including GPS location) because the pixels are redrawn.
 */
final class WebpImage
{
    public static function make(string $contents, int $maxWidth, int $quality = 72): string
    {
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw new InvalidArgumentException('Not a picture');
        }
        $width = imagesx($image);
        if ($width > $maxWidth) {
            $scaled = imagescale($image, $maxWidth, (int) round(imagesy($image) * $maxWidth / $width));
            if ($scaled !== false) {
                imagedestroy($image);
                $image = $scaled;
            }
        }
        imagepalettetotruecolor($image);
        ob_start();
        imagewebp($image, null, $quality);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
