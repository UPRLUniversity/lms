<?php

namespace App\Models;

use App\Casts\RichHtml;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use App\Enums\EnrollmentMode;
use App\Enums\EnrollmentStatus;
use App\Enums\MediaPurpose;
use App\Enums\ProgressionMode;
use App\Models\Concerns\HasMedia;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, HasMedia;

    protected $fillable = [
        'title',
        'slug',
        'code',
        'department_id',
        'level',
        'summary',
        'description',
        'learning_objectives',
        'duration_minutes',
        'status',
        'visibility',
        'enrollment_mode',
        'progression_mode',
        'capacity',
        'enrollment_opens_at',
        'enrollment_closes_at',
        'created_by',
        'review_note',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'level' => CourseLevel::class,
            'visibility' => CourseVisibility::class,
            'enrollment_mode' => EnrollmentMode::class,
            'progression_mode' => ProgressionMode::class,
            'capacity' => 'integer',
            'enrollment_opens_at' => 'datetime',
            'enrollment_closes_at' => 'datetime',
            'description' => RichHtml::class,
            'learning_objectives' => 'array',
            'duration_minutes' => 'integer',
            'published_at' => 'datetime',
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
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Course ↔ instructors, with a lead-instructor flag on the pivot.
     *
     * @return BelongsToMany<User, $this>
     */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_instructor')
            ->withPivot('is_lead')
            ->withTimestamps()
            ->orderByDesc('course_instructor.is_lead');
    }

    /**
     * @return HasMany<Module, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('position');
    }

    /**
     * Every lesson in the course, across its modules.
     *
     * @return HasManyThrough<Lesson, Module, $this>
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Course>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', CourseStatus::Published->value);
    }

    /**
     * Courses that belong on the public catalogue: published AND publicly visible.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeInCatalogue(Builder $query): void
    {
        $query->where('status', CourseStatus::Published->value)
            ->where('visibility', CourseVisibility::PublicCatalogue->value);
    }

    /**
     * Courses a given instructor teaches (or created).
     *
     * @param  Builder<Course>  $query
     */
    public function scopeForInstructor(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('instructors', fn (Builder $i) => $i->whereKey($user->id));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function cover(): ?Media
    {
        return $this->firstMediaFor(MediaPurpose::CourseCovers);
    }

    public function coverUrl(): ?string
    {
        return $this->cover()?->url;
    }

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    /**
     * Whether $user is one of this course's instructors (or its creator).
     */
    public function isTaughtBy(User $user): bool
    {
        if ($this->created_by === $user->id) {
            return true;
        }

        return $this->instructors->contains(fn (User $i) => $i->is($user))
            || $this->instructors()->whereKey($user->id)->exists();
    }

    /**
     * Lesson count without loading every lesson (uses a loaded count when present).
     */
    public function lessonCount(): int
    {
        return (int) ($this->lessons_count ?? $this->lessons()->count());
    }

    /**
     * Total duration across every lesson, in minutes.
     */
    public function totalDurationMinutes(): int
    {
        if ($this->relationLoaded('modules')) {
            return (int) $this->modules->sum(
                fn (Module $module) => $module->relationLoaded('lessons')
                    ? $module->lessons->sum('duration_minutes')
                    : $module->lessons()->sum('duration_minutes'),
            );
        }

        return (int) $this->lessons()->sum('duration_minutes');
    }

    public function leadInstructor(): ?User
    {
        return $this->instructors->firstWhere('pivot.is_lead', true)
            ?? $this->instructors->first();
    }

    /**
     * Whether $user is the lead instructor of this course — the (co-)instructor who
     * may run its approval queue, alongside admins.
     */
    public function isLeadInstructor(User $user): bool
    {
        return $this->instructors()
            ->wherePivot('is_lead', true)
            ->whereKey($user->id)
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Enrolment
    |--------------------------------------------------------------------------
    */

    public function enrollmentMode(): EnrollmentMode
    {
        return $this->enrollment_mode ?? EnrollmentMode::Open;
    }

    public function progressionMode(): ProgressionMode
    {
        return $this->progression_mode ?? ProgressionMode::Free;
    }

    public function isSequential(): bool
    {
        return $this->progressionMode()->isSequential();
    }

    public function hasCapacityLimit(): bool
    {
        return $this->capacity !== null;
    }

    /**
     * Seats currently occupied (active + pending). Uses a withCount-loaded value when
     * present (rosters/lists), else a direct count.
     */
    public function seatsTaken(): int
    {
        if ($this->seats_taken_count !== null) {
            return (int) $this->seats_taken_count;
        }

        return (int) $this->enrollments()->occupyingSeat()->count();
    }

    /**
     * Seats left, or null when the course is uncapped.
     */
    public function seatsAvailable(): ?int
    {
        if (! $this->hasCapacityLimit()) {
            return null;
        }

        return max(0, (int) $this->capacity - $this->seatsTaken());
    }

    public function isFull(): bool
    {
        return $this->hasCapacityLimit() && $this->seatsTaken() >= (int) $this->capacity;
    }

    public function enrollmentOpensInFuture(): bool
    {
        return $this->enrollment_opens_at !== null && $this->enrollment_opens_at->isFuture();
    }

    public function enrollmentHasClosed(): bool
    {
        return $this->enrollment_closes_at !== null && $this->enrollment_closes_at->isPast();
    }

    /**
     * Whether the enrolment window is open right now (null bounds = no limit).
     */
    public function enrollmentWindowOpen(): bool
    {
        return ! $this->enrollmentOpensInFuture() && ! $this->enrollmentHasClosed();
    }

    /**
     * Whether a student could, in principle, self-enrol right now: a published course,
     * not invite-only, inside its window. Capacity is handled separately (full ⇒
     * waitlist), so it is intentionally NOT part of this gate.
     */
    public function selfEnrollmentOpen(): bool
    {
        return $this->isPublished()
            && $this->enrollmentMode()->allowsSelfEnrollment()
            && $this->enrollmentWindowOpen();
    }

    /**
     * A given user's enrollment for this course, if any (uses a loaded relation when
     * present to avoid a per-card query).
     */
    public function enrollmentFor(User $user): ?Enrollment
    {
        if ($this->relationLoaded('enrollments')) {
            return $this->enrollments->firstWhere('user_id', $user->id);
        }

        return $this->enrollments()->where('user_id', $user->id)->first();
    }

    public function isEnrolled(User $user): bool
    {
        $enrollment = $this->enrollmentFor($user);

        return $enrollment !== null
            && in_array($enrollment->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true);
    }
}
