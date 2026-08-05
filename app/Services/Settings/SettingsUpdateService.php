<?php

namespace App\Services\Settings;

use App\Enums\AuditEvent;
use App\Enums\MediaPurpose;
use App\Enums\SettingGroup;
use App\Models\GradeScale;
use App\Models\Media;
use App\Services\Grades\GradeScaleService;
use App\Services\Media\MediaUploadService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Facades\Activity;

/**
 * Applies one tab of /admin/settings: scalar values, brand-artwork uploads, and the
 * settings whose real home is another table.
 *
 * Three things happen here that the controller should not be doing itself:
 *
 *   1. DERIVED SETTINGS. "Default grade scale" looks like a setting but is really
 *      GradeScale.is_default. It is delegated to GradeScaleService so the "exactly
 *      one default" invariant keeps a single writer, rather than being re-implemented
 *      behind a second screen.
 *   2. MEDIA LIFECYCLE. Replacing a logo uploads the new file, repoints the setting
 *      and deletes the old one — in that order, so a failed upload never leaves the
 *      institution with no logo.
 *   3. AUDIT. Every change is recorded as ONE entry carrying the whole diff, not one
 *      entry per field.
 */
class SettingsUpdateService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly MediaUploadService $media,
        private readonly GradeScaleService $scales,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $values  scalar settings, dotted keys
     * @param  array<string, UploadedFile>  $uploads  new brand artwork, dotted keys
     * @param  array<int, string>  $cleared  media settings reverted to the packaged asset
     * @return array<string, array{old: mixed, new: mixed}> what actually moved
     */
    public function apply(SettingGroup $group, array $values, array $uploads = [], array $cleared = []): array
    {
        $changes = DB::transaction(function () use ($group, $values, $uploads, $cleared) {
            $changes = $this->settings->set($this->withoutDerived($values));

            $changes += $this->applyDerived($group, $values);
            $changes += $this->applyUploads($uploads);
            $changes += $this->applyClears($cleared);

            return $changes;
        });

        if ($changes !== []) {
            $this->audit->record(
                AuditEvent::SettingsUpdated,
                null,
                [
                    'group' => $group->value,
                    'old' => array_map(fn (array $c) => $c['old'], $changes),
                    'attributes' => array_map(fn (array $c) => $c['new'], $changes),
                ],
                'Changed '.$group->label().' settings',
            );
        }

        return $changes;
    }

    /**
     * Derived settings are filtered out of the ordinary write path — the settings
     * table must never hold a stale copy of state that lives elsewhere.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutDerived(array $values): array
    {
        return array_filter(
            $values,
            fn (string $key) => ! (SettingsRepository::definition($key)['derived'] ?? false),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function applyDerived(SettingGroup $group, array $values): array
    {
        if ($group !== SettingGroup::Grading || ! array_key_exists('grading.default_scale_id', $values)) {
            return [];
        }

        $scaleId = (int) $values['grading.default_scale_id'];

        if ($scaleId <= 0) {
            return [];
        }

        $scale = GradeScale::find($scaleId);

        if ($scale === null || $scale->is_default) {
            return [];
        }

        // The model write is logged silently: flipping is_default would otherwise
        // produce a second, weaker entry ("Grade Scale X was updated") alongside the
        // deliberate one below, which names both the old and new scale. One change
        // should read as one entry.
        $previous = Activity::withoutLogs(fn () => $this->scales->makeDefault($scale));

        // Recorded against the scale itself, so "why did this course's grades start
        // rendering differently?" is answerable from the scale's own history.
        $this->audit->recordChange(
            AuditEvent::DefaultScaleChanged,
            $scale,
            ['default_grade_scale' => $previous?->name],
            ['default_grade_scale' => $scale->name],
            [],
            "“{$scale->name}” became the system default grade scale",
        );

        return ['grading.default_scale_id' => [
            'old' => $previous?->name ?? '—',
            'new' => $scale->name,
        ]];
    }

    /**
     * @param  array<string, UploadedFile>  $uploads
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function applyUploads(array $uploads): array
    {
        $changes = [];

        foreach ($uploads as $key => $file) {
            $previousId = $this->settings->int($key);

            // Upload FIRST. If MediaUploadService rejects the file, the existing
            // artwork is still in place and still referenced.
            $media = $this->media->upload($file, MediaPurpose::BrandAssets);

            $this->settings->set([$key => (string) $media->id]);

            $changes[$key] = [
                'old' => $previousId ? "media #{$previousId}" : 'packaged artwork',
                'new' => $file->getClientOriginalName(),
            ];

            $this->discard($previousId);
        }

        return $changes;
    }

    /**
     * @param  array<int, string>  $cleared
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function applyClears(array $cleared): array
    {
        $changes = [];

        foreach ($cleared as $key) {
            $previousId = $this->settings->int($key);

            if (! $previousId) {
                continue;
            }

            $this->settings->set([$key => null]);
            $this->discard($previousId);

            $changes[$key] = ['old' => "media #{$previousId}", 'new' => 'packaged artwork'];
        }

        return $changes;
    }

    /**
     * Delete a superseded brand asset. Never fatal: an orphaned file is a tidiness
     * problem, whereas a failed settings save over one is a broken screen.
     */
    private function discard(?int $mediaId): void
    {
        if (! $mediaId) {
            return;
        }

        try {
            if ($media = Media::find($mediaId)) {
                $this->media->destroy($media);
            }
        } catch (\Throwable) {
            // Left on disk; the settings row no longer points at it.
        }
    }
}
