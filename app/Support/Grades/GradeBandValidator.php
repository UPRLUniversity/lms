<?php

namespace App\Support\Grades;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Server-side invariants for a grade scale's bands, enforced on every save (the sole
 * gate — nothing else may write bands). Named after the flawed example scale from the
 * team discussion that violated all three: contiguous 0–100 coverage, strictly
 * decreasing grade points from the top band down, and nothing above the scale limit.
 */
class GradeBandValidator
{
    /**
     * @param  array<int, array{label: string, grade_point: float|int|string, min_percent: int|string, max_percent: int|string}>  $bands
     */
    public static function validate(array $bands, float $scaleLimit): void
    {
        if (count($bands) < 2) {
            throw ValidationException::withMessages([
                'bands' => 'A grade scale needs at least two bands.',
            ]);
        }

        $rows = collect($bands)->map(fn (array $b) => [
            'label' => trim((string) $b['label']),
            'grade_point' => (float) $b['grade_point'],
            'min_percent' => (int) $b['min_percent'],
            'max_percent' => (int) $b['max_percent'],
        ])->values();

        foreach ($rows as $row) {
            if ($row['min_percent'] < 0 || $row['max_percent'] > 100) {
                throw ValidationException::withMessages([
                    'bands' => "“{$row['label']}” must stay within 0–100%.",
                ]);
            }

            if ($row['min_percent'] > $row['max_percent']) {
                throw ValidationException::withMessages([
                    'bands' => "“{$row['label']}” has a minimum ({$row['min_percent']}) above its maximum ({$row['max_percent']}).",
                ]);
            }
        }

        $labels = $rows->map(fn (array $r) => Str::lower($r['label']));
        if ($labels->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'bands' => 'Band labels must be unique within a scale.',
            ]);
        }

        // Coverage: sorted ascending by min_percent, the bottom band up. A cursor tracks
        // the next percentage point that must be covered; any mismatch names the exact
        // gap or overlap range.
        $sorted = $rows->sortBy('min_percent')->values();
        $cursor = 0;

        foreach ($sorted as $row) {
            if ($row['min_percent'] > $cursor) {
                throw ValidationException::withMessages([
                    'bands' => "Coverage gap: {$cursor}–".($row['min_percent'] - 1).' is not covered by any band.',
                ]);
            }

            if ($row['min_percent'] < $cursor) {
                throw ValidationException::withMessages([
                    'bands' => "Overlap: {$row['min_percent']}–".($cursor - 1).' is covered by more than one band.',
                ]);
            }

            $cursor = $row['max_percent'] + 1;
        }

        if ($cursor <= 100) {
            throw ValidationException::withMessages([
                'bands' => "Coverage gap: {$cursor}–100 is not covered by any band.",
            ]);
        }

        // Monotonicity: walking bottom → top, grade points must strictly increase (i.e.
        // strictly decrease from the top band down).
        for ($i = 1; $i < $sorted->count(); $i++) {
            if ($sorted[$i]['grade_point'] <= $sorted[$i - 1]['grade_point']) {
                throw ValidationException::withMessages([
                    'bands' => "“{$sorted[$i]['label']}” covers a higher range than “{$sorted[$i - 1]['label']}” but doesn't carry a higher grade point.",
                ]);
            }
        }

        foreach ($rows as $row) {
            if ($row['grade_point'] > $scaleLimit) {
                throw ValidationException::withMessages([
                    'bands' => "“{$row['label']}”'s grade point ({$row['grade_point']}) exceeds the scale limit ({$scaleLimit}).",
                ]);
            }
        }
    }
}
