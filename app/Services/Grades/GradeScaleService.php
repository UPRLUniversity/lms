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
