<?php

namespace App\Services\Assignments;

use App\Models\Rubric;
use Illuminate\Support\Facades\DB;

/**
 * Persists the rubric grid as posted: name + ordered criteria, each with its ordered
 * levels JSON. Criteria are rebuilt wholesale on save — grades are unaffected because
 * they snapshot their selections at grading time.
 */
class RubricService
{
    /**
     * @param  array{name: string, criteria: array<int, array<string, mixed>>}  $data
     */
    public function save(Rubric $rubric, array $data): Rubric
    {
        return DB::transaction(function () use ($rubric, $data) {
            $rubric->name = $data['name'];
            $rubric->save();

            $rubric->criteria()->delete();

            foreach (array_values($data['criteria']) as $position => $criterion) {
                $rubric->criteria()->create([
                    'title' => $criterion['title'],
                    'description' => $criterion['description'] ?? null,
                    'levels' => collect(array_values($criterion['levels']))
                        ->map(fn (array $level) => [
                            'label' => (string) $level['label'],
                            'description' => (string) ($level['description'] ?? ''),
                            'points' => (float) $level['points'],
                        ])->all(),
                    'position' => $position,
                ]);
            }

            return $rubric->refresh()->load('criteria');
        });
    }
}
