<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GradeScaleStatus;
use App\Enums\SettingGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\GradeScale;
use App\Models\Media;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SettingsUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * System settings (Section 15). Super-admin only — see the manage-settings gate.
 *
 * One tab per SettingGroup, each posted independently, so saving General cannot
 * disturb Commerce. The form is generated from the schema in config/settings.php:
 * this controller never names an individual setting, which is what keeps adding one
 * a config-only change.
 *
 * Deliberately NOT re-implemented here: gateway credentials (Section 12's payment
 * methods screen owns those, encrypted) and the grade-band grid (Section 6.5's scale
 * admin). Both are linked to instead.
 */
class SettingController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function index(?string $group = null): View
    {
        Gate::authorize('manage-settings');

        $active = SettingGroup::tryFrom((string) $group) ?? SettingGroup::General;

        return view('admin.settings.index', [
            'groups' => SettingGroup::ordered(),
            'active' => $active,
            'definitions' => SettingsRepository::definitionsFor($active),
            'values' => $this->settings->all(),
            'options' => $this->options(),
            'assets' => $this->currentAssets(),
        ]);
    }

    public function update(SettingsRequest $request, SettingsUpdateService $updater): RedirectResponse
    {
        $group = $request->group();

        try {
            $changes = $updater->apply(
                $group,
                $request->settingValues(),
                $request->uploadedAssets(),
                $request->clearedAssets(),
            );
        } catch (ValidationException $e) {
            return back()
                ->withInput()
                ->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('admin.settings.index', $group->value)
            ->with('status', $changes === []
                ? 'No changes to save.'
                : $this->summarise($changes));
    }

    /**
     * Values for every `select` whose options are resolved at runtime rather than
     * declared in config.
     *
     * @return array<string, array<string, string>>
     */
    private function options(): array
    {
        return [
            'locales' => config('settings.locales', []),
            'date_formats' => $this->dateFormatOptions(),

            // Archived scales are omitted: making a retired scale the system default
            // would silently repoint every course that has no override of its own.
            'grade_scales' => GradeScale::query()
                ->where('status', GradeScaleStatus::Active)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->map(fn (string $name) => $name)
                ->all(),

            'timezones' => $this->timezoneOptions(),
        ];
    }

    /**
     * Each format rendered against TODAY, so the administrator picks by looking at
     * a real date rather than decoding "d M Y".
     *
     * @return array<string, string>
     */
    private function dateFormatOptions(): array
    {
        $options = [];

        foreach (array_keys(config('settings.date_formats', [])) as $format) {
            $options[$format] = now()->format($format);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function timezoneOptions(): array
    {
        $zones = [];

        foreach (timezone_identifiers_list() as $zone) {
            $zones[$zone] = str_replace('_', ' ', $zone);
        }

        return $zones;
    }

    /**
     * The Media rows the branding settings currently point at, so the form can show
     * a live preview beside each upload field.
     *
     * @return array<string, Media>
     */
    private function currentAssets(): array
    {
        $assets = [];

        foreach (SettingsRepository::definitions() as $key => $definition) {
            if (($definition['type'] ?? null) !== 'media') {
                continue;
            }

            if ($id = $this->settings->int($key)) {
                if ($media = Media::find($id)) {
                    $assets[$key] = $media;
                }
            }
        }

        return $assets;
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    private function summarise(array $changes): string
    {
        $count = count($changes);
        $labels = array_map(
            fn (string $key) => strtolower(SettingsRepository::definition($key)['label'] ?? $key),
            array_keys($changes),
        );

        if ($count <= 3) {
            return 'Saved — updated '.$this->joinWords($labels).'.';
        }

        return "Saved — {$count} settings updated.";
    }

    /**
     * @param  array<int, string>  $words
     */
    private function joinWords(array $words): string
    {
        if (count($words) === 1) {
            return $words[0];
        }

        $last = array_pop($words);

        return implode(', ', $words).' and '.$last;
    }
}
