<?php

namespace App\Models;

use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\GradeBandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a grade scale: the inclusive percentage range it covers, the letter/label
 * it displays and the grade point it contributes. Rebuilt wholesale whenever its scale
 * is saved (mirrors RubricCriterion) — safe because completion snapshots copy the whole
 * scale into their own JSON rather than referencing these rows.
 */
class GradeBand extends Model
{
    /** @use HasFactory<GradeBandFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'grade_scale_id',
        'label',
        'grade_point',
        'min_percent',
        'max_percent',
        'color',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'grade_point' => 'decimal:2',
            'min_percent' => 'integer',
            'max_percent' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GradeScale, $this>
     */
    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    public function covers(float $percent): bool
    {
        return $percent >= $this->min_percent && $percent <= $this->max_percent;
    }

    /**
     * @return array{label: string, grade_point: float, min_percent: int, max_percent: int, color: string}
     */
    public function toSnapshot(): array
    {
        return [
            'label' => $this->label,
            'grade_point' => (float) $this->grade_point,
            'min_percent' => $this->min_percent,
            'max_percent' => $this->max_percent,
            'color' => $this->color,
        ];
    }
}
