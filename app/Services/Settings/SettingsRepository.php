<?php

namespace App\Services\Settings;

use App\Enums\SettingGroup;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The single reader/writer for runtime settings.
 *
 * The `settings` table stores only what has actually been CHANGED from the schema
 * in config/settings.php. Reading therefore resolves a three-step fallback:
 *
 *   stored row  →  the definition's `default`  →  the `config` key it points at
 *
 * The last step is what lets a setting sit transparently on top of an existing
 * config value: with no row present, config('brand.university') is still the file's
 * value, and that is exactly what the getter returns. Once a row exists,
 * SettingsServiceProvider pushes it INTO config at boot, so both readers agree.
 *
 * The resolved map is cached indefinitely because it is read on nearly every
 * request, and busted on write.
 */
class SettingsRepository
{
    /** In-request memo, so repeated reads in one request never re-hit the cache store. */
    private ?array $resolved = null;

    /**
     * Every setting, resolved to its effective value.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        return $this->resolved = Cache::rememberForever(
            $this->cacheKey(),
            fn () => $this->resolveFromDatabase(),
        );
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->all()[$key] ?? $fallback;
    }

    public function string(string $key, string $fallback = ''): string
    {
        $value = $this->get($key);

        return is_scalar($value) ? (string) $value : $fallback;
    }

    public function bool(string $key, bool $fallback = false): bool
    {
        $value = $this->get($key);

        return $value === null ? $fallback : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function int(string $key, ?int $fallback = null): ?int
    {
        $value = $this->get($key);

        return $value === null || $value === '' ? $fallback : (int) $value;
    }

    /**
     * Persist a batch of changes and return ONLY what actually moved, as
     * ['key' => ['old' => …, 'new' => …]] — the shape the audit log records.
     *
     * A value equal to its schema default is stored anyway once it has been
     * explicitly saved: "the admin chose this" and "nobody has ever looked" are
     * different states, and only the former should survive a change to the
     * default in a future release.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function set(array $values): array
    {
        // Resolve fresh rather than trusting the memo. This object is a singleton, so
        // its cached map can predate anything that changed the underlying data since it
        // was built — most sharply during `migrate:fresh --seed`, where the map is
        // captured at boot and the table is then dropped and rebuilt beneath it. A
        // writer comparing against a stale "before" silently decides nothing changed
        // and writes nothing at all.
        $this->flush();

        $before = $this->all();
        $changes = [];

        foreach ($values as $key => $value) {
            $definition = static::definition($key);

            if ($definition === null || ($definition['derived'] ?? false)) {
                continue;   // unknown key, or one whose real home is another table
            }

            $cast = $this->cast($value, $definition['type'] ?? 'string');

            if ($this->same($before[$key] ?? null, $cast)) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $cast, 'group' => ($definition['group'] ?? null)?->value],
            );

            $changes[$key] = ['old' => $before[$key] ?? null, 'new' => $cast];
        }

        if ($changes !== []) {
            $this->flush();
        }

        return $changes;
    }

    public function flush(): void
    {
        $this->resolved = null;
        Cache::forget($this->cacheKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Schema helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>|null
     */
    public static function definition(string $key): ?array
    {
        // NOT config("settings.definitions.{$key}"): setting keys contain dots, and
        // config() resolves those as nesting — it would look for a `general` array
        // holding a `university_name` child, not the literal key
        // "general.university_name". Index the resolved array directly instead.
        return static::definitions()[$key] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return config('settings.definitions', []);
    }

    /**
     * The definitions belonging to one tab, in declaration order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitionsFor(SettingGroup $group): array
    {
        return array_filter(
            static::definitions(),
            fn (array $definition) => ($definition['group'] ?? null) === $group,
        );
    }

    /**
     * Whether a setting's value must never appear in an audit diff.
     */
    public static function isSecret(string $key): bool
    {
        return (bool) (static::definition($key)['secret'] ?? false);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function resolveFromDatabase(): array
    {
        // Before the table exists (a fresh clone running `migrate` for the first
        // time, or `config:cache` in a build step) settings simply are their
        // defaults — booting must never depend on the schema being present.
        $stored = $this->tableExists()
            ? Setting::query()->pluck('value', 'key')->all()
            : [];

        $resolved = [];

        foreach (static::definitions() as $key => $definition) {
            $type = $definition['type'] ?? 'string';

            if (array_key_exists($key, $stored)) {
                $resolved[$key] = $this->cast($stored[$key], $type);

                continue;
            }

            $default = $definition['default'] ?? null;

            // A null default with a config pointer means "inherit the file value".
            if ($default === null && isset($definition['config'])) {
                $default = config($definition['config']);
            }

            $resolved[$key] = $default === null ? null : $this->cast($default, $type);
        }

        return $resolved;
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            // No database configured yet (e.g. `composer install` post-scripts).
            return false;
        }
    }

    private function cast(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return $type === 'bool' ? false : null;
        }

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int', 'media' => (int) $value,
            default => (string) $value,
        };
    }

    /**
     * Loose-but-typed comparison, so "8" arriving from a form does not read as a
     * change against a stored integer 8.
     */
    private function same(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }

        return (string) $a === (string) $b;
    }

    private function cacheKey(): string
    {
        return config('settings.cache_key', 'uprl:settings');
    }
}
