<?php

namespace App\Models;

use App\Services\Settings\SettingsRepository;
use Illuminate\Database\Eloquent\Model;

/**
 * One CHANGED setting. Absence means "still the schema default" — see
 * App\Services\Settings\SettingsRepository, which is the only thing that should
 * read or write this table directly.
 *
 * Deliberately not activity-logged at the model level: a settings save is recorded
 * once, as a single audit entry carrying the whole diff (SettingController), rather
 * than as one row-update entry per field.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    /**
     * Values are stored as text and cast on read by the repository, according to
     * the type declared in config/settings.php — so the schema stays the single
     * source of truth for what a setting IS.
     */
    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Any write to this table busts the resolved-settings cache.
     *
     * SettingsRepository::set() already flushes, and that is the path the application
     * uses. This is the backstop for everything else — a console command, a future
     * feature, a maintenance script — that writes a Setting directly and would
     * otherwise leave the cache serving values that no longer exist.
     *
     * Note the limit: a mass delete through the query builder
     * (Setting::where(...)->delete()) fires no model events and is not caught here.
     * Anything doing that must call SettingsRepository::flush() itself.
     */
    protected static function booted(): void
    {
        $bust = fn () => app(SettingsRepository::class)->flush();

        static::saved($bust);
        static::deleted($bust);
    }
}
