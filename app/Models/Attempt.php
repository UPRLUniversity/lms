<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One student's run at an assessment. The `layout` is frozen at start — the ordered
 * question ids, their per-question option/pair presentation order, and (for pooled) the
 * drawn selection — so a refresh never reshuffles and every submitted answer can be
 * validated against a fixed, server-held structure.
 */
class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'assessment_id',
        'user_id',
        'attempt_number',
        'started_at',
        'submitted_at',
        'expires_at',
        'score',
        'max_score',
        'percentage',
        'passed',
        'status',
        'layout',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'percentage' => 'integer',
            'passed' => 'boolean',
            'attempt_number' => 'integer',
            'layout' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Layout helpers — the frozen presentation
    |--------------------------------------------------------------------------
    */

    /**
     * The ordered question rows in this attempt's frozen layout.
     *
     * @return array<int, array<string, mixed>>
     */
    public function layoutQuestions(): array
    {
        return array_values($this->layout['questions'] ?? []);
    }

    /**
     * The frozen layout row for a single question id, or null when the question isn't part
     * of this attempt (a tamper attempt).
     *
     * @return array<string, mixed>|null
     */
    public function layoutFor(int $questionId): ?array
    {
        foreach ($this->layoutQuestions() as $row) {
            if ((int) ($row['id'] ?? 0) === $questionId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The set of question ids this attempt legitimately contains.
     *
     * @return array<int, int>
     */
    public function questionIds(): array
    {
        return array_map(fn (array $row) => (int) $row['id'], $this->layoutQuestions());
    }

    public function includesQuestion(int $questionId): bool
    {
        return in_array($questionId, $this->questionIds(), true);
    }

    /*
    |--------------------------------------------------------------------------
    | Timing
    |--------------------------------------------------------------------------
    */

    public function isTimed(): bool
    {
        return $this->expires_at !== null;
    }

    /**
     * Server-authoritative: whether an in-progress, timed attempt has run out.
     */
    public function isExpired(): bool
    {
        return $this->isTimed() && $this->expires_at->isPast();
    }

    /**
     * Whole seconds left on the clock (0 when expired/untimed-with-no-deadline).
     */
    public function remainingSeconds(): ?int
    {
        if (! $this->isTimed()) {
            return null;
        }

        return max(0, now()->diffInSeconds($this->expires_at, false));
    }

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    public function isInProgress(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    public function isGraded(): bool
    {
        return $this->status === AttemptStatus::Graded;
    }

    /**
     * Whether any answer still awaits a human grader (an ungraded essay/scenario item).
     */
    public function hasPendingManual(): bool
    {
        return $this->answers()->whereNull('points_awarded')->exists();
    }
}
