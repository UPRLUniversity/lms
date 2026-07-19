<?php

namespace App\Models;

use Database\Factories\RubricCriterionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a rubric grid. `levels` is the ordered column list:
 * [{label: "Excellent", description: "...", points: 10}, ...]. Grading selects one
 * level per criterion; the server reads the points from here, never from the client.
 */
class RubricCriterion extends Model
{
    /** @use HasFactory<RubricCriterionFactory> */
    use HasFactory;

    protected $table = 'rubric_criteria';

    protected $fillable = [
        'rubric_id',
        'title',
        'description',
        'levels',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'levels' => 'array',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Rubric, $this>
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    /**
     * The level at a given index, or null when out of range.
     *
     * @return array{label: string, description?: string, points: float|int}|null
     */
    public function level(int $index): ?array
    {
        return $this->levels[$index] ?? null;
    }

    public function maxPoints(): float
    {
        return (float) collect($this->levels ?? [])->max(fn (array $l) => (float) ($l['points'] ?? 0));
    }
}
