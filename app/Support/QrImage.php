<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders QR codes locally.
 *
 * Deliberately not a hosted image API: the payload identifies a real payment,
 * so it should never be handed to a third party just to draw some squares.
 */
class QrImage
{
    public static function svg(string $payload, int $size = 320): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, margin: 1),
            new SvgImageBackEnd(),
        ));

        return $writer->writeString($payload);
    }

    /** Inline-able form, so the page needs no extra request. */
    public static function svgDataUri(string $payload, int $size = 320): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($payload, $size));
    }
}
