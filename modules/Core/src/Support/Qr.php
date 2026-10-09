<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR codes drawn on the server as SVG - no third-party service sees the links. */
final class Qr
{
    public static function svg(string $text, int $size = 320): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($text);
    }
}
