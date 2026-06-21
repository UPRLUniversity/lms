<?php

namespace App\Models;

use App\Enums\LessonProgressStatus;
use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's progress through one lesson. The (user_id, lesson_id) pair is unique at
 * the DB level, so the row is the single, idempotent record of completion — a double
 * "complete" can never create a second row or double-count.
 */
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'status',
        'completed_at',
        'seconds_spent',
        'last_position_seconds',
    ];

    protected function casts(): array
    {
        return [
            'status' => LessonProgressStatus::class,
            'completed_at' => 'datetime',
            'seconds_spent' => 'integer',
            'last_position_seconds' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isComplete(): bool
    {
        return $this->status === LessonProgressStatus::Completed;
    }
}
