<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaPurpose;
use App\Enums\SettingGroup;
use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * One tab of /admin/settings, posted whole.
 *
 * Setting keys are dotted ("general.university_name"), which HTML form names and
 * Laravel's dot-notation validator would both read as nesting. They therefore travel
 * over the wire with the dot encoded as "__" — settings[general__university_name] —
 * and are decoded back here, in the one place that knows about the encoding.
 *
 * Validation rules are not written out: they are READ from the schema in
 * config/settings.php, so adding a setting there needs no change in this class.
 */
class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-settings');
    }

    /**
     * The tab being saved. Only that tab's fields are validated and written, so
     * saving General cannot blank a field on Commerce that was never on screen.
     */
    public function group(): SettingGroup
    {
        return SettingGroup::tryFrom((string) $this->input('group', 'general'))
            ?? SettingGroup::General;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'group' => ['required', 'string'],
        ];

        foreach (SettingsRepository::definitionsFor($this->group()) as $key => $definition) {
            $field = 'settings.'.static::encode($key);
            $type = $definition['type'] ?? 'string';

            // A media setting carries no scalar value in `settings` — the file
            // arrives on `uploads`, validated below.
            if ($type === 'media') {
                $rules['uploads.'.static::encode($key)] = $this->mediaRules($definition);

                continue;
            }

            $rules[$field] = $this->fieldRules($definition, $type);
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, mixed>
     */
    private function fieldRules(array $definition, string $type): array
    {
        $rules = $definition['rules'] ?? [];

        // A checkbox is absent from the payload when unticked, so a bool can never
        // be "required" — normalize() supplies the false.
        if ($type === 'bool') {
            return ['nullable', 'boolean'];
        }

        if ($type === 'select' && ($options = $this->optionValues($definition)) !== []) {
            $rules[] = 'in:'.implode(',', $options);
        }

        return $rules;
    }

    /**
     * Brand artwork. The mime/size ceiling is NOT restated here — it lives in
     * config/media.php against MediaPurpose::BrandAssets, and the upload service
     * re-validates on the way in regardless of what this request allowed.
     *
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function mediaRules(array $definition): array
    {
        $purpose = MediaPurpose::BrandAssets;

        return [
            'nullable',
            'file',
            'mimetypes:'.implode(',', $purpose->allowedMimes()),
            'max:'.$purpose->maxKb(),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, string>
     */
    private function optionValues(array $definition): array
    {
        $options = $definition['options'] ?? [];

        // A string points at a list elsewhere in config/settings.php (locales,
        // date_formats) or at a runtime set (grade_scales) resolved by the
        // controller — the latter cannot be range-checked here.
        if (is_string($options)) {
            $resolved = config("settings.{$options}");

            return is_array($resolved) ? array_map('strval', array_keys($resolved)) : [];
        }

        return array_map('strval', array_keys($options));
    }

    /**
     * The posted values, decoded back to dotted keys and restricted to this tab.
     *
     * Unticked checkboxes are filled in as false: HTML omits them entirely, and
     * without this an administrator could switch a toggle on but never off.
     *
     * @return array<string, mixed>
     */
    public function settingValues(): array
    {
        $posted = (array) $this->input('settings', []);
        $values = [];

        foreach (SettingsRepository::definitionsFor($this->group()) as $key => $definition) {
            $type = $definition['type'] ?? 'string';

            if ($type === 'media') {
                continue;   // handled as an upload, not a value
            }

            $encoded = static::encode($key);

            if ($type === 'bool') {
                $values[$key] = array_key_exists($encoded, $posted)
                    ? filter_var($posted[$encoded], FILTER_VALIDATE_BOOLEAN)
                    : false;

                continue;
            }

            if (array_key_exists($encoded, $posted)) {
                $values[$key] = $posted[$encoded];
            }
        }

        return $values;
    }

    /**
     * Uploaded brand artwork, keyed by dotted setting key.
     *
     * @return array<string, UploadedFile>
     */
    public function uploadedAssets(): array
    {
        $files = [];

        foreach (SettingsRepository::definitionsFor($this->group()) as $key => $definition) {
            if (($definition['type'] ?? null) !== 'media') {
                continue;
            }

            if ($file = $this->file('uploads.'.static::encode($key))) {
                $files[$key] = $file;
            }
        }

        return $files;
    }

    /**
     * Media settings the administrator asked to clear (revert to the packaged
     * artwork), keyed by dotted setting key.
     *
     * @return array<int, string>
     */
    public function clearedAssets(): array
    {
        $cleared = array_keys(array_filter((array) $this->input('clear', [])));

        return array_values(array_filter(
            array_map(static fn (string $encoded) => static::decode($encoded), $cleared),
            static fn (string $key) => (SettingsRepository::definition($key)['type'] ?? null) === 'media',
        ));
    }

    public static function encode(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public static function decode(string $field): string
    {
        return str_replace('__', '.', $field);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (SettingsRepository::definitions() as $key => $definition) {
            $label = strtolower($definition['label'] ?? $key);
            $attributes['settings.'.static::encode($key)] = $label;
            $attributes['uploads.'.static::encode($key)] = $label;
        }

        return $attributes;
    }
}
