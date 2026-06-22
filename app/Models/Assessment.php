<?php

namespace App\Models;

use App\Casts\RichHtml;
use App\Enums\AssessmentPlacement;
use App\Enums\AssessmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\ReviewPolicy;
use App\Enums\SelectionMode;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quiz or exam attached to a course's curriculum. Holds the settings (timing, attempt
 * limits, shuffle, review policy, passing score) and — depending on selection_mode — an
 * explicit ordered question list (fixed) or a set of pool rules (pooled). The taking
 * engine and grading read everything they need from here and the frozen attempt layout.
 */
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    protected $fillable = [
        'course_id',
        'module_id',
        'created_by',
        'title',
        'slug',
        'instructions',
        'placement',
        'status',
        'selection_mode',
        'passing_score',
        'max_attempts',
        'time_limit_minutes',
        'available_from',
        'available_until',
        'shuffle_questions',
        'shuffle_options',
        'review_policy',
        'show_explanations',
        'is_required',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'placement' => AssessmentPlacement::class,
            'status' => AssessmentStatus::class,
            'selection_mode' => SelectionMode::class,
            'review_policy' => ReviewPolicy::class,
            'instructions' => RichHtml::class,
            'passing_score' => 'integer',
            'max_attempts' => 'integer',
            'time_limit_minutes' => 'integer',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'show_explanations' => 'boolean',
            'is_required' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Fixed-mode question list, ordered by pivot position.
     *
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'assessment_questions')
            ->withPivot(['position', 'points_override'])
            ->withTimestamps()
            ->orderBy('assessment_questions.position');
    }

    /**
     * @return HasMany<AssessmentPoolRule, $this>
     */
    public function poolRules(): HasMany
    {
        return $this->hasMany(AssessmentPoolRule::class)->orderBy('position');
    }

    /**
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Assessment>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', AssessmentStatus::Published->value);
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    public function isPublished(): bool
    {
        return $this->status === AssessmentStatus::Published;
    }

    public function isPooled(): bool
    {
        return $this->selection_mode === SelectionMode::Pooled;
    }

    public function isTimed(): bool
    {
        return $this->time_limit_minutes !== null && $this->time_limit_minutes > 0;
    }

    /**
     * Number of questions a student will answer in one attempt: the fixed list size, or
     * the sum of the pool rule counts.
     */
    public function questionCount(): int
    {
        if ($this->isPooled()) {
            return (int) ($this->relationLoaded('poolRules')
                ? $this->poolRules->sum('count')
                : $this->poolRules()->sum('count'));
        }

        return (int) ($this->relationLoaded('questions')
            ? $this->questions->count()
            : $this->questions()->count());
    }

    /**
     * Total points available on a fixed assessment (override falls back to the question's
     * own points). Pooled totals are only known once an attempt is drawn, so this returns
     * null for pooled.
     */
    public function totalPoints(): ?float
    {
        if ($this->isPooled()) {
            return null;
        }

        return (float) $this->questions->sum(
            fn (Question $q) => $q->pivot->points_override ?? $q->points,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Availability window
    |--------------------------------------------------------------------------
    */

    public function opensInFuture(): bool
    {
        return $this->available_from !== null && $this->available_from->isFuture();
    }

    public function hasClosed(): bool
    {
        return $this->available_until !== null && $this->available_until->isPast();
    }

    /**
     * Whether right now is inside the assessment's availability window.
     */
    public function withinWindow(): bool
    {
        return ! $this->opensInFuture() && ! $this->hasClosed();
    }

    /*
    |--------------------------------------------------------------------------
    | Per-student attempt accounting
    |--------------------------------------------------------------------------
    */

    /**
     * Every attempt a given user has on this assessment, newest first.
     *
     * @return Collection<int, Attempt>
     */
    public function attemptsFor(User $user): Collection
    {
        return $this->attempts()
            ->where('user_id', $user->id)
            ->orderByDesc('attempt_number')
            ->get();
    }

    public function attemptsUsedBy(User $user): int
    {
        return $this->attempts()->where('user_id', $user->id)->count();
    }

    /**
     * Attempts a user has left, or null when the assessment allows unlimited attempts.
     */
    public function attemptsLeftFor(User $user): ?int
    {
        if ($this->max_attempts === null) {
            return null;
        }

        return max(0, $this->max_attempts - $this->attemptsUsedBy($user));
    }

    /**
     * Whether a student may begin a *new* attempt right now: published, inside the window,
     * with attempts remaining and no attempt already in progress.
     */
    public function canStart(User $user): bool
    {
        if (! $this->isPublished() || ! $this->withinWindow()) {
            return false;
        }

        $left = $this->attemptsLeftFor($user);
        if ($left !== null && $left <= 0) {
            return false;
        }

        return ! $this->attempts()
            ->where('user_id', $user->id)
            ->where('status', AttemptStatus::InProgress->value)
            ->exists();
    }

    /**
     * The student's current in-progress attempt, if any (so the start screen can resume).
     */
    public function inProgressAttemptFor(User $user): ?Attempt
    {
        return $this->attempts()
            ->where('user_id', $user->id)
            ->where('status', AttemptStatus::InProgress->value)
            ->latest('id')
            ->first();
    }

    /**
     * A student's best graded attempt (highest percentage), for progress + history.
     */
    public function bestAttemptFor(User $user): ?Attempt
    {
        return $this->attempts()
            ->where('user_id', $user->id)
            ->where('status', AttemptStatus::Graded->value)
            ->orderByDesc('percentage')
            ->first();
    }
}
