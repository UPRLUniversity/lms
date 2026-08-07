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
use App\Models\Concerns\LogsAuditActivity;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, HasMedia, LogsAuditActivity;

    protected $fillable = [
        'title',
        'slug',
        'code',
        'department_id',
        'level',
        'price_override',
        'is_free',
        'summary',
        'description',
        'learning_objectives',
        'duration_minutes',
        'status',
        'visibility',
        'enrollment_mode',
        'progression_mode',
        'grade_scale_id',
        'certificate_template_id',
        'capacity',
        'enrollment_opens_at',
        'enrollment_closes_at',
        'created_by',
        'review_note',
        'published_at',
    ];

    /**
     * Mirrors the database default so a newly created (un-refreshed) instance already
     * knows it is free. Without this, is_free reads null in memory until the model is
     * reloaded, and PricingService would price a brand-new course from its programme's
     * fee when the stored row says otherwise.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_free' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'level' => CourseLevel::class,
            'visibility' => CourseVisibility::class,
            'enrollment_mode' => EnrollmentMode::class,
            'progression_mode' => ProgressionMode::class,
            'price_override' => 'decimal:2',
            'is_free' => 'boolean',
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
     * The programme parts this course is placed in — the second classification axis
     * alongside department. Many-to-many because the published curriculum genuinely
     * lists the same paper under more than one programme (CPR 112 and CPR 115 each
     * appear in both CPR Part I and Professional Variant Part 1).
     *
     * credit_load and requirement ride on the pivot because they are properties of
     * the placement, not of the course.
     *
     * @return BelongsToMany<ProgrammePart, $this>
     */
    public function programmeParts(): BelongsToMany
    {
        return $this->belongsToMany(ProgrammePart::class, 'course_programme_part')
            // The explicit pivot model is what casts requirement to CourseRequirement and
            // credit_load to int — without it every read is a raw string and the views
            // would have to re-hydrate the enum by hand.
            ->using(CourseProgrammePart::class)
            ->withPivot(['credit_load', 'requirement', 'is_primary', 'position'])
            ->withTimestamps()
            ->orderBy('course_programme_part.position');
    }

    /**
     * This course's grade-scale override, if any (null = system default).
     *
     * @return BelongsTo<GradeScale, $this>
     */
    public function gradeScale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class);
    }

    /**
     * @return HasMany<CourseGradeRecord, $this>
     */
    public function courseGradeRecords(): HasMany
    {
        return $this->hasMany(CourseGradeRecord::class);
    }

    /**
     * This course's certificate-template override, if any (null = system default).
     *
     * @return BelongsTo<CertificateTemplate, $this>
     */
    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
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

    /**
     * Assessments attached to this course (pre/post on its modules + standalone).
     *
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * Assignments attached to this course (on its modules + standalone).
     *
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * The course-scoped question bank.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Instructor-posted updates, newest first (Section 8).
     *
     * @return HasMany<CourseAnnouncement, $this>
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(CourseAnnouncement::class)->latest();
    }

    /**
     * Categories the course's questions are grouped into.
     *
     * @return HasMany<QuestionCategory, $this>
     */
    public function questionCategories(): HasMany
    {
        return $this->hasMany(QuestionCategory::class);
    }

    /**
     * Discussion threads in this course's forum (Section 9).
     *
     * @return HasMany<ForumThread, $this>
     */
    public function forumThreads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Membership (forums & messaging — Section 9)
    |--------------------------------------------------------------------------
    */

    /**
     * The students with learning access — actively enrolled or already completed.
     * The audience for a course group conversation and the classmate pool for direct
     * messages.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function enrolledStudents(): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->whereHas('enrollments', fn (Builder $q) => $q->where('course_id', $this->id)
                ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value]))
            ->get();
    }

    /**
     * Everyone who belongs to this course's community: its instructors plus its
     * enrolled/completed students. The membership pool for messaging classmates and
     * instructors, and the roster of a "message all enrolled" group.
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        // The owner counts even when they were never added to the course_instructor
        // pivot — isTaughtBy, isMember and the forInstructor scope all treat created_by
        // as teaching this course, and members() disagreeing with them meant an owner
        // could message their students while never appearing in those students' own
        // contact lists.
        $owner = $this->created_by
            ? User::query()->whereKey($this->created_by)->get()
            : collect();

        return $this->instructors()->get()
            ->concat($owner)
            ->concat($this->enrolledStudents())
            ->unique('id')
            ->values();
    }

    /**
     * Whether $user belongs to this course's community — an enrolled/completed student
     * or one of its instructors. The gate for reading and posting in its forum, and
     * for messaging within it.
     */
    public function isMember(User $user): bool
    {
        $enrollment = $this->enrollmentFor($user);

        if ($enrollment && $enrollment->grantsLearningAccess()) {
            return true;
        }

        return $this->isTaughtBy($user);
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
    | Programme placement (Section 11)
    |--------------------------------------------------------------------------
    */

    /**
     * The placement that decides this course's identity when it sits in more than one
     * programme — the one flagged primary, falling back to the first placement.
     *
     * Section 12 resolves the course's inherited price from this placement's
     * programme, which is the whole reason the flag exists: a paper listed in both CPR
     * (₦7,000) and the Professional Variant (₦15,000) otherwise has no single price.
     */
    public function primaryPlacement(): ?ProgrammePart
    {
        $parts = $this->relationLoaded('programmeParts')
            ? $this->programmeParts
            : $this->programmeParts()->get();

        return $parts->firstWhere(fn (ProgrammePart $p) => (bool) $p->pivot->is_primary)
            ?? $parts->first();
    }

    public function primaryProgramme(): ?Programme
    {
        return $this->primaryPlacement()?->programme;
    }

    /**
     * Replace this course's placements wholesale.
     *
     * Takes rows of [programme_part_id, credit_load, requirement, is_primary] and
     * normalises them before writing: exactly one row may be primary (the first one
     * claiming it wins; if none does, the first row is promoted), so
     * primaryPlacement() always has a deterministic answer. This is the only place
     * placements are written — the "at most one primary" rule is an application
     * invariant because a partial unique index is not portable to sqlite.
     *
     * @param  array<int, array{programme_part_id: int|string, credit_load?: int|string|null, requirement?: string|null, is_primary?: bool}>  $rows
     */
    public function syncProgrammePlacements(array $rows): void
    {
        $seenPrimary = false;
        $sync = [];
        $position = 1;

        foreach ($rows as $row) {
            $partId = (int) ($row['programme_part_id'] ?? 0);
            if ($partId <= 0 || isset($sync[$partId])) {
                continue;   // skip blank repeater rows and duplicate parts
            }

            $wantsPrimary = (bool) ($row['is_primary'] ?? false);
            $isPrimary = $wantsPrimary && ! $seenPrimary;
            $seenPrimary = $seenPrimary || $isPrimary;

            $credit = $row['credit_load'] ?? null;

            $sync[$partId] = [
                'credit_load' => ($credit === null || $credit === '') ? null : (int) $credit,
                'requirement' => ($row['requirement'] ?? null) ?: null,
                'is_primary' => $isPrimary,
                'position' => $position++,
            ];
        }

        // A course with placements always has exactly one primary.
        if (! $seenPrimary && $sync !== []) {
            $first = array_key_first($sync);
            $sync[$first]['is_primary'] = true;
        }

        $this->programmeParts()->sync($sync);
        $this->unsetRelation('programmeParts');
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

    /*
    |--------------------------------------------------------------------------
    | Grading (Section 6.5)
    |--------------------------------------------------------------------------
    */

    /**
     * The scale that governs this course's grades: its own override, or the system
     * default when it has none. Used for display and for freezing a completion
     * snapshot — never for re-deriving an already-recorded grade.
     */
    public function gradeScaleOrDefault(): ?GradeScale
    {
        if ($this->relationLoaded('gradeScale') && $this->gradeScale !== null) {
            return $this->gradeScale;
        }

        if ($this->grade_scale_id !== null) {
            return $this->gradeScale()->with('bands')->first();
        }

        return GradeScale::query()->default()->with('bands')->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Certificates (Section 7)
    |--------------------------------------------------------------------------
    */

    /**
     * The template that governs this course's certificates: its own override, or the
     * system default when it has none.
     */
    public function certificateTemplateOrDefault(): ?CertificateTemplate
    {
        if ($this->relationLoaded('certificateTemplate') && $this->certificateTemplate !== null) {
            return $this->certificateTemplate;
        }

        if ($this->certificate_template_id !== null) {
            return $this->certificateTemplate()->first();
        }

        return CertificateTemplate::query()->default()->first();
    }
}
