<?php

namespace App\Models;

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
}
