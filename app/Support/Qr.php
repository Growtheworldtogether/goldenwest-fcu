<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

final class Qr
{
    /** An inline SVG. The text is encoded, never echoed, so the markup is safe to print unescaped. */
    public static function svg(string $text, int $size = 200): string
    {
        $svg = (new Builder(writer: new SvgWriter(), data: $text, size: $size, margin: 6))->build()->getString();

        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
