<?php

use App\Services\Branding\BrandAssets;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    /**
     * Read a runtime setting (Section 15).
     *
     *   setting('general.support_email')
     *   setting('security.force_email_verification', true)
     *
     * Most settings also override a config key, so config('brand.university') and
     * setting('general.university_name') return the same thing — reach for whichever
     * reads better where you are. Use this one when the setting has no config
     * counterpart (date format, login tagline, the locale switcher flag).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsRepository::class)->get($key, $default);
    }
}

if (! function_exists('brand_assets')) {
    /**
     * The brand-artwork resolver — logos and favicon, whether uploaded from
     * /admin/settings or shipped in public/images/brand/.
     */
    function brand_assets(): BrandAssets
    {
        return app(BrandAssets::class);
    }
}

if (! function_exists('display_date')) {
    /**
     * Format a date in the administrator's chosen format (Settings → General).
     * Null in, empty string out, so views need no @if wrapper.
     */
    function display_date(mixed $date, ?string $format = null): string
    {
        if (empty($date)) {
            return '';
        }

        return Carbon::parse($date)
            ->format($format ?? (string) setting('general.date_format', 'd M Y'));
    }
}

if (! function_exists('display_datetime')) {
    /**
     * As display_date(), plus a 24-hour clock — the app never shows AM/PM.
     */
    function display_datetime(mixed $date): string
    {
        if (empty($date)) {
            return '';
        }

        return Carbon::parse($date)
            ->format((string) setting('general.date_format', 'd M Y').', H:i');
    }
}
