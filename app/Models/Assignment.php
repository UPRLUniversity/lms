<?php

namespace App\Models;

use App\Casts\RichHtml;
use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\MediaPurpose;
use App\Enums\SubmissionStatus;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A graded piece of coursework the student hands in (file and/or text), sitting in the
 * curriculum like lessons and assessments. Submissions are versioned (1..n, prior
 * versions immutable); grading is rubric-based or direct points against max_points.
 */
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory, HasMedia, LogsAuditActivity;

    protected $fillable = [
        'course_id',
        'module_id',
        'created_by',
        'title',
        'slug',
        'instructions',
        'type',
        'due_at',
        'allow_late',
        'max_points',
        'rubric_id',
        'status',
        'is_required',
        'counts_toward_grade',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'instructions' => RichHtml::class,
            'type' => AssignmentType::class,
            'status' => AssignmentStatus::class,
            'due_at' => 'datetime',
            'allow_late' => 'boolean',
            'max_points' => 'decimal:2',
            'is_required' => 'boolean',
            'counts_toward_grade' => 'boolean',
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
     * @return BelongsTo<Rubric, $this>
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & state
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Assignment>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', AssignmentStatus::Published->value);
    }

    public function isPublished(): bool
    {
        return $this->status === AssignmentStatus::Published;
    }

    public function hasDueDate(): bool
    {
        return $this->due_at !== null;
    }

    public function isPastDue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    /**
     * Whether a submission made right now would be accepted: always before the due
     * date; after it only when late submissions are allowed.
     */
    public function acceptsSubmissionsNow(): bool
    {
        return ! $this->isPastDue() || $this->allow_late;
    }

    /**
     * Instructor-attached briefs/templates (private files).
     *
     * @return Collection<int, Media>
     */
    public function resources(): Collection
    {
        return $this->mediaFor(MediaPurpose::AssignmentResources);
    }

    /*
    |--------------------------------------------------------------------------
    | Per-student submission accounting
    |--------------------------------------------------------------------------
    */

    /**
     * Every version a student has handed in, newest first.
     *
     * @return Collection<int, Submission>
     */
    public function submissionsFor(User $user): Collection
    {
        return $this->submissions()
            ->where('user_id', $user->id)
            ->orderByDesc('version')
            ->get();
    }

    /**
     * The student's current (highest-version) submission, if any.
     */
    public function latestSubmissionFor(User $user): ?Submission
    {
        return $this->submissions()
            ->where('user_id', $user->id)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Whether the student has a graded submission — the completion signal that counts
     * toward course progress (a grade later returned for resubmission stops counting).
     */
    public function isCompletedBy(User $user): bool
    {
        return $this->submissions()
            ->where('user_id', $user->id)
            ->where('status', SubmissionStatus::Graded->value)
            ->exists();
    }
}
