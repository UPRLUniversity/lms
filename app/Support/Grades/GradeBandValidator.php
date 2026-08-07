<?php

namespace App\Support\Grades;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Server-side invariants for a grade scale's bands, enforced on every save (the sole
 * gate — nothing else may write bands). Named after the flawed example scale from the
 * team discussion that violated all three: contiguous 0–100 coverage, strictly
 * decreasing grade points from the top band down, and nothing above the scale limit.
 *
 * Section 18 adds the pass invariants: a scale must be able to express BOTH outcomes, and
 * passing bands must sit at the top, so "pass mark: 40%" is always a true statement about
 * the scale rather than a label over an interleaved mess.
 */
class GradeBandValidator
{
    /**
     * @param  array<int, array{label: string, grade_point: float|int|string, is_pass?: bool|int|string, min_percent: int|string, max_percent: int|string}>  $bands
     */
    public static function validate(array $bands, float $scaleLimit): void
    {
        if (count($bands) < 2) {
            throw ValidationException::withMessages([
                'bands' => 'A grade scale needs at least two bands.',
            ]);
        }

        // A band that doesn't state `is_pass` is not treated as a pass — and the
        // at-least-one-passing-band rule below turns that into a loud error rather than a
        // scale that quietly passes nobody.
        $rows = collect($bands)->map(fn (array $b) => [
            'label' => trim((string) $b['label']),
            'grade_point' => (float) $b['grade_point'],
            'is_pass' => filter_var($b['is_pass'] ?? false, FILTER_VALIDATE_BOOLEAN),
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

        self::validatePassOutcomes($sorted);
    }

    /**
     * A scale must be able to express both outcomes, and passing must be the TOP of the
     * scale. Without the second rule "pass mark: 40%" stops being true the moment somebody
     * marks a middle band as a fail, and every screen quoting it would be lying.
     *
     * @param  Collection<int, array{label: string, is_pass: bool}>  $sorted  ascending by min_percent
     */
    private static function validatePassOutcomes(Collection $sorted): void
    {
        if (! $sorted->contains(fn (array $r) => $r['is_pass'])) {
            throw ValidationException::withMessages([
                'bands' => 'Mark at least one band as a pass — otherwise no student on this scale could ever pass.',
            ]);
        }

        if (! $sorted->contains(fn (array $r) => ! $r['is_pass'])) {
            throw ValidationException::withMessages([
                'bands' => 'Mark at least one band as a fail — otherwise no student on this scale could ever fail.',
            ]);
        }

        // Walking bottom → top, once a band passes every band above it must pass too.
        $seenPass = false;
        foreach ($sorted as $row) {
            if ($row['is_pass']) {
                $seenPass = true;

                continue;
            }

            if ($seenPass) {
                throw ValidationException::withMessages([
                    'bands' => "“{$row['label']}” is marked as a fail but a lower band passes. Passing bands must be the top of the scale.",
                ]);
            }
        }
    }
}
