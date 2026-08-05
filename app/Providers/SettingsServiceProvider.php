<?php

namespace App\Providers;

use App\Services\Branding\BrandAssets;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Pushes stored settings INTO config at boot.
 *
 * This is the mechanism behind "change it in Settings and it takes effect
 * everywhere". Rather than teaching every call site about a settings table, each
 * setting may declare the config key it overrides (config/settings.php → `config`),
 * and this provider applies those overrides once per request, before anything
 * reads them. Code that already said config('brand.university') or
 * config('commerce.currency') keeps working, unchanged, and now honours the
 * administrator's choice.
 *
 * Only settings with a STORED row produce an override — an untouched setting
 * resolves to the config file's own value, so applying it would be a no-op anyway.
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singletons: the resolved settings map and the memoised brand-asset
        // lookups should be built at most once per request.
        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(BrandAssets::class);
    }

    public function boot(): void
    {
        // Console commands that run BEFORE the schema exists (migrate, config:cache
        // in a build step) must not be blocked by a missing settings table. The
        // repository already degrades to defaults; this guard keeps a broken
        // database connection from taking the whole app down with it.
        try {
            $settings = $this->app->make(SettingsRepository::class)->all();
        } catch (\Throwable) {
            return;
        }

        $this->applyConfigOverrides($settings);
        $this->applyTimezone($settings);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function applyConfigOverrides(array $settings): void
    {
        $overrides = [];

        foreach (SettingsRepository::definitions() as $key => $definition) {
            $target = $definition['config'] ?? null;

            if ($target === null) {
                continue;
            }

            $value = $settings[$key] ?? null;

            // Null means "nothing stored, nothing defaulted" — leave the config
            // file's own value alone rather than blanking it.
            if ($value === null) {
                continue;
            }

            $overrides[$target] = $value;
        }

        if ($overrides !== []) {
            config($overrides);
        }
    }

    /**
     * The timezone needs more than a config key. Laravel applies app.timezone once,
     * during the LoadConfiguration bootstrapper, long before this provider boots —
     * so overriding the config value alone would leave PHP's default pointing at
     * whatever the file said. Re-applying it here is what actually moves dates.
     *
     * @param  array<string, mixed>  $settings
     */
    private function applyTimezone(array $settings): void
    {
        $timezone = $settings['general.timezone'] ?? null;

        if (! is_string($timezone) || $timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        date_default_timezone_set($timezone);
    }
}
