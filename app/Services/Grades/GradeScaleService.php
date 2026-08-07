<?php

namespace App\Services\Grades;

use App\Enums\GradeScaleStatus;
use App\Models\GradeScale;
use App\Support\Grades\GradeBandValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists the scale + its bands wholesale on every save (mirrors RubricService),
 * behind the invariant validator, and keeps "exactly one default" true by construction.
 */
class GradeScaleService
{
    /**
     * @param  array{name: string, scale_limit: float|string, is_default?: bool, display_mode: string, show_scale_limit?: bool, separator?: string, bands: array<int, array<string, mixed>>}  $data
     */
    public function save(GradeScale $scale, array $data): GradeScale
    {
        $scaleLimit = (float) $data['scale_limit'];

        GradeBandValidator::validate($data['bands'], $scaleLimit);

        return DB::transaction(function () use ($scale, $data, $scaleLimit) {
            $wantsDefault = (bool) ($data['is_default'] ?? false);

            // Never leave zero defaults: unchecking the sole default is a no-op, not an
            // error — the human must make another scale the default first.
            if (! $wantsDefault && $scale->exists && $scale->is_default
                && ! GradeScale::query()->default()->where('id', '!=', $scale->id)->exists()) {
                $wantsDefault = true;
            }

            $scale->fill([
                'name' => $data['name'],
                'scale_limit' => $scaleLimit,
                'display_mode' => $data['display_mode'],
                'show_scale_limit' => (bool) ($data['show_scale_limit'] ?? true),
                'separator' => $data['separator'] ?? '/',
                'is_default' => $wantsDefault,
                'status' => $scale->status ?? GradeScaleStatus::Active,
            ]);
            $scale->save();

            if ($wantsDefault) {
                GradeScale::query()->where('id', '!=', $scale->id)->update(['is_default' => false]);
            }

            $scale->bands()->delete();
            foreach (array_values($data['bands']) as $position => $band) {
                $scale->bands()->create([
                    'label' => trim((string) $band['label']),
                    'grade_point' => (float) $band['grade_point'],
                    'is_pass' => filter_var($band['is_pass'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'min_percent' => (int) $band['min_percent'],
                    'max_percent' => (int) $band['max_percent'],
                    'color' => $band['color'] ?? 'neutral',
                    'position' => $position,
                ]);
            }

            return $scale->refresh()->load('bands');
        });
    }

    /**
     * Promote a scale to system default (Section 15's Settings → Grading selector).
     *
     * Kept here rather than in the settings screen so "exactly one default" stays
     * enforced in ONE place: the settings page is a second door onto this state, not
     * a second owner of it. Returns the scale that WAS the default, or null if there
     * was none — the caller needs it for the audit diff.
     *
     * An archived scale is refused: it would leave every course without an override
     * pointing at a scale the admin has already retired.
     */
    public function makeDefault(GradeScale $scale): ?GradeScale
    {
        if ($scale->status === GradeScaleStatus::Archived) {
            throw ValidationException::withMessages([
                'grading.default_scale_id' => 'An archived scale cannot be the system default. Restore it first.',
            ]);
        }

        return DB::transaction(function () use ($scale) {
            $previous = GradeScale::query()->default()->where('id', '!=', $scale->id)->first();

            if ($scale->is_default && $previous === null) {
                return null;   // already the default; nothing moved
            }

            GradeScale::query()->where('id', '!=', $scale->id)->update(['is_default' => false]);
            $scale->forceFill(['is_default' => true])->save();

            return $previous;
        });
    }

    /**
     * Archive, never delete. Blocked on the current default — the platform must always
     * have exactly one active default scale.
     */
    public function archive(GradeScale $scale): GradeScale
    {
        if ($scale->is_default) {
            throw ValidationException::withMessages([
                'status' => 'Set another scale as the system default before archiving this one.',
            ]);
        }

        $scale->update(['status' => GradeScaleStatus::Archived]);

        return $scale;
    }

    public function restore(GradeScale $scale): GradeScale
    {
        $scale->update(['status' => GradeScaleStatus::Active]);

        return $scale;
    }
}
