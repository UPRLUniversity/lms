<?php

namespace App\Models;

use App\Casts\RichHtml;
use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The instructor's verdict on one submission version. criterion_scores snapshots the
 * rubric selections exactly as graded (id, title, level, points), so the student's
 * breakdown is stable even if the rubric is edited afterwards. points_total is always
 * recomputed server-side.
 */
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'grader_id',
        'criterion_scores',
        'points_total',
        'feedback',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'criterion_scores' => 'array',
            'points_total' => 'decimal:2',
            'feedback' => RichHtml::class.':basic',
            'graded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grader_id');
    }
}
