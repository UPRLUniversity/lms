<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;

/**
 * Sanitizes rich HTML on the way INTO the database, so any rich-text attribute is
 * made safe just by casting it — a feature section cannot forget. Pair with the
 * matching editor profile:
 *
 *   protected $casts = [
 *       'body'    => RichHtml::class,            // 'rich'
 *       'message' => RichHtml::class.':basic',   // 'basic'
 *   ];
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class RichHtml implements CastsAttributes
{
    public function __construct(private string $profile = 'rich') {}

    /**
     * Stored value is already clean (sanitized on set).
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    /**
     * Sanitize against the allow-list profile before persisting.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value === '' ? '' : null;
        }

        return Purifier::clean((string) $value, $this->profile);
    }
}
