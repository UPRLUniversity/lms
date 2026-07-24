<?php

namespace App\Services\Certificates;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Small, dependency-free asset builders for the certificate PDF: the sunburst
 * watermark (the same ray/circle geometry as <x-brand.sunburst>, reproduced here as a
 * static SVG string since dompdf renders a real PDF, not a Blade/Alpine component) and
 * the verification QR. Both render as SVG — this environment has neither GD nor
 * Imagick, so PNG output is unavailable; SVG needs neither (bacon/bacon-qr-code's SVG
 * backend is pure PHP, and dompdf embeds SVG images natively via php-svg-lib).
 */
class CertificateAsset
{
    /**
     * The UPRL sunburst motif as a base64 SVG data URI, tinted to $hexColor at low
     * opacity — a subtle watermark, never visual noise.
     */
    public static function sunburstDataUri(string $hexColor, int $rays = 24, float $opacity = 0.08): string
    {
        $lines = '';

        for ($i = 0; $i < $rays; $i++) {
            $angle = ($i / $rays) * 360;
            $inner = $i % 2 === 0 ? 48 : 40;
            $outer = $i % 2 === 0 ? 96 : 82;
            $rad = deg2rad($angle);
            $x1 = 100 + $inner * cos($rad);
            $y1 = 100 + $inner * sin($rad);
            $x2 = 100 + $outer * cos($rad);
            $y2 = 100 + $outer * sin($rad);

            $lines .= sprintf(
                '<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" />',
                $x1, $y1, $x2, $y2
            );
        }

        $svg = <<<SVG
            <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <g stroke="{$hexColor}" stroke-width="2" stroke-linecap="round" opacity="{$opacity}" fill="none">
                    {$lines}
                    <circle cx="100" cy="100" r="30" />
                </g>
                <circle cx="100" cy="100" r="14" fill="{$hexColor}" opacity="{$opacity}" />
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * A QR code pointing at the given URL, as a base64 SVG data URI.
     */
    public static function qrDataUri(string $url, int $size = 220): string
    {
        $svg = QrCode::format('svg')->size($size)->margin(0)->generate($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * The "Modern" layout's crimson diagonal corner motif — built as static SVG
     * geometry (not a CSS transform, which dompdf's CSS support doesn't reliably
     * render) so it's guaranteed to appear identically in the PDF and the preview.
     */
    public static function diagonalMotifDataUri(string $hexColor): string
    {
        $svg = <<<SVG
            <svg viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
                <polygon points="400,0 400,300 160,300" fill="{$hexColor}" opacity="0.10" />
                <polygon points="400,0 400,220 240,0" fill="{$hexColor}" opacity="0.18" />
                <polygon points="400,0 400,120 320,0" fill="{$hexColor}" opacity="0.28" />
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
