<?php

namespace App\Services\Branding;

use App\Models\Media;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves a brand asset — the three logo variants and the favicon — to whatever
 * the consumer needs, from whichever source is currently in force.
 *
 * There are two sources and one precedence rule:
 *
 *   1. An image uploaded via /admin/settings (stored as a Media id). Wins.
 *   2. The file named in config/brand.php, shipped in public/images/brand/.
 *
 * and two consumer shapes:
 *
 *   url()      → an <img src> for HTML (app chrome, public site)
 *   dataUri()  → base64 bytes for renderers that cannot fetch a URL — dompdf
 *                (certificates, report PDFs) and the e-mail header, which must
 *                survive being read offline in a mail client
 *
 * Every brand-artwork consumer in the app goes through this class, which is what
 * makes "change the logo in Settings" reach all of them without a code change.
 * Results are memoised per request: a certificate batch would otherwise re-read
 * or re-fetch the same logo once per PDF.
 */
class BrandAssets
{
    public const VARIANTS = ['color', 'white', 'mark'];

    /** @var array<string, string|null> */
    private array $urls = [];

    /** @var array<string, string|null> */
    private array $dataUris = [];

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * A URL for the given logo variant, or null when neither an upload nor a
     * shipped file exists — callers render their own fallback (the monogram in
     * <x-brand.logo>, a text wordmark in the PDF layouts).
     */
    public function url(string $variant = 'color'): ?string
    {
        return $this->urls[$variant] ??= $this->resolveUrl($variant);
    }

    /**
     * The variant's bytes as a data URI, for dompdf and e-mail. Never throws: a
     * missing or unreadable asset returns null and the caller degrades to text.
     */
    public function dataUri(string $variant = 'color'): ?string
    {
        return $this->dataUris[$variant] ??= $this->resolveDataUri($variant);
    }

    /**
     * The browser-tab icon. Falls back through the shipped .ico and .png so an
     * install that has uploaded nothing still gets the packaged artwork.
     */
    public function faviconUrl(): ?string
    {
        if ($media = $this->media('branding.favicon')) {
            return $this->mediaUrl($media);
        }

        return $this->packagedUrl(config('brand.icons.favicon'));
    }

    public function faviconPngUrl(): ?string
    {
        if ($media = $this->media('branding.favicon')) {
            return $this->mediaUrl($media);
        }

        return $this->packagedUrl(config('brand.icons.favicon_png'));
    }

    public function appleTouchUrl(): ?string
    {
        if ($media = $this->media('branding.favicon')) {
            return $this->mediaUrl($media);
        }

        return $this->packagedUrl(config('brand.icons.apple_touch'));
    }

    /**
     * Whether any brand artwork at all is available for this variant — lets a view
     * decide between an <img> and its own fallback without resolving twice.
     */
    public function has(string $variant = 'color'): bool
    {
        return $this->url($variant) !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution
    |--------------------------------------------------------------------------
    */

    private function resolveUrl(string $variant): ?string
    {
        if ($media = $this->media("branding.logo_{$variant}")) {
            return $this->mediaUrl($media);
        }

        return $this->packagedUrl(config("brand.logos.{$variant}"));
    }

    private function resolveDataUri(string $variant): ?string
    {
        if ($media = $this->media("branding.logo_{$variant}")) {
            return $this->mediaDataUri($media);
        }

        $path = config("brand.logos.{$variant}");

        if (! $path || ! file_exists(public_path($path))) {
            return null;
        }

        return $this->encode(
            (string) file_get_contents(public_path($path)),
            $this->mimeFromPath($path),
        );
    }

    /**
     * The Media row an upload-type setting points at, or null when unset/deleted.
     * A dangling id (the row was removed out from under the setting) resolves to
     * null rather than erroring, so branding can never break the whole app.
     */
    private function media(string $key): ?Media
    {
        $id = $this->settings->int($key);

        return $id ? Media::find($id) : null;
    }

    private function mediaUrl(Media $media): ?string
    {
        if ($media->url) {
            return $media->url;
        }

        return $media->path
            ? Storage::disk($media->disk)->url($media->path)
            : null;
    }

    /**
     * Bytes for an uploaded asset — read straight off the disk for a local upload,
     * fetched once for a remote (Cloudinary) one. Mirrors CertificateRenderer's
     * handling of signature images.
     */
    private function mediaDataUri(Media $media): ?string
    {
        try {
            if ($media->provider === 'local' && $media->path && Storage::disk($media->disk)->exists($media->path)) {
                return $this->encode(
                    (string) Storage::disk($media->disk)->get($media->path),
                    $media->mime ?: 'image/png',
                );
            }

            if ($media->url) {
                $bytes = @file_get_contents($media->url);

                return $bytes === false ? null : $this->encode($bytes, $media->mime ?: 'image/png');
            }
        } catch (\Throwable) {
            // Branding is decoration: a broken asset must never fail a certificate.
        }

        return null;
    }

    /**
     * A public_path-relative file shipped with the app. Returns null when the file
     * is absent, so a fresh clone with no artwork degrades to the monogram.
     */
    private function packagedUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return file_exists(public_path($path)) ? asset($path) : null;
    }

    private function encode(string $bytes, string $mime): string
    {
        return "data:{$mime};base64,".base64_encode($bytes);
    }

    private function mimeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            default => 'image/png',
        };
    }
}
