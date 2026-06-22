<?php

namespace App\Models;

use App\Casts\RichHtml;
use Database\Factories\AttemptAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's answer to one question within an attempt. `response` is the raw answer
 * (shape per question type); the grading columns are filled by the auto-grader on submit
 * or by an instructor for manual (essay) items.
 */
class AttemptAnswer extends Model
{
    /** @use HasFactory<AttemptAnswerFactory> */
    use HasFactory;

    protected $fillable = [
        'attempt_id',
        'question_id',
        'response',
        'is_correct',
        'points_awarded',
        'points_possible',
        'feedback',
        'graded_by',
        'graded_at',
        'flagged',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'is_correct' => 'boolean',
            'points_awarded' => 'decimal:2',
            'points_possible' => 'decimal:2',
            'feedback' => RichHtml::class.':basic',
            'graded_at' => 'datetime',
            'flagged' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function isGraded(): bool
    {
        return $this->points_awarded !== null;
    }

    public function isAnswered(): bool
    {
        return $this->response !== null && $this->response !== [] && $this->response !== '';
    }
}
