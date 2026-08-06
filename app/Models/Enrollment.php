<?php

namespace App\Models;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Models\Concerns\LogsAuditActivity;
use App\Support\Curriculum\CompletionSnapshot;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single student's place in a course. The (user_id, course_id) pair is unique at
 * the DB level, so a duplicate enrollment is impossible by construction; re-enrolling
 * after withdrawing/rejection updates the same row rather than creating a second one.
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory, LogsAuditActivity;

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'source',
        'enrolled_at',
        'progress_percent',
        'completed_at',
        'approved_by',
        'decision_note',
        'pending_digested_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'source' => EnrollmentSource::class,
            'enrolled_at' => 'datetime',
            'progress_percent' => 'integer',
            'completed_at' => 'datetime',
            'pending_digested_at' => 'datetime',
            'completion_snapshot' => 'array',
            'completion_snapshot_at' => 'datetime',
        ];
    }

    /**
     * The completion snapshot is written once, by LearningService, at the instant the
     * enrollment reaches Completed — and never rewritten. Guarded here rather than only
     * at the call site so no future path can quietly restate what a finished student was
     * measured against (the same append-only stance as AuditActivity and
     * Certificate.snapshot).
     */
    protected static function booted(): void
    {
        static::updating(function (Enrollment $enrollment): void {
            if ($enrollment->isDirty('completion_snapshot')
                && $enrollment->getOriginal('completion_snapshot') !== null) {
                throw new \RuntimeException(
                    'A completion snapshot is written once and never rewritten. '
                    .'Recompute reads it; it does not replace it.',
                );
            }
        });
    }

    /**
     * The frozen curriculum this student was measured against, or null while they are
     * still working through the course (and so still follow the live one).
     */
    public function completionSnapshot(): ?CompletionSnapshot
    {
        return CompletionSnapshot::fromArray($this->completion_snapshot);
    }

    public function hasCompletionSnapshot(): bool
    {
        return $this->completion_snapshot !== null && $this->completion_snapshot !== [];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The staff member who approved (or directly enrolled) this student, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<Enrollment>  $query
     */
    public function scopeWithStatus(Builder $query, EnrollmentStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * Enrollments occupying a seat (active or pending) — the basis of capacity.
     *
     * @param  Builder<Enrollment>  $query
     */
    public function scopeOccupyingSeat(Builder $query): void
    {
        $query->whereIn('status', EnrollmentStatus::seatHolders());
    }

    /**
     * The waitlist, earliest first (FIFO). Ties on the join time break by id so the
     * ordering is total and stable under concurrent inserts.
     *
     * @param  Builder<Enrollment>  $query
     */
    public function scopeWaitlistOrder(Builder $query): void
    {
        $query->where('status', EnrollmentStatus::Waitlisted->value)
            ->orderBy('enrolled_at')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * This enrollment's 1-based position in its course's waitlist, or null when it
     * isn't waitlisted. Derived (never stored), so positions renumber for free the
     * moment anyone ahead is promoted or leaves.
     */
    public function waitlistPosition(): ?int
    {
        if ($this->status !== EnrollmentStatus::Waitlisted) {
            return null;
        }

        $ahead = static::query()
            ->where('course_id', $this->course_id)
            ->where('status', EnrollmentStatus::Waitlisted->value)
            ->where(function (Builder $q) {
                $q->where('enrolled_at', '<', $this->enrolled_at)
                    ->orWhere(function (Builder $tie) {
                        $tie->where('enrolled_at', '=', $this->enrolled_at)
                            ->where('id', '<', $this->id);
                    });
            })
            ->count();

        return $ahead + 1;
    }

    public function isWaitlisted(): bool
    {
        return $this->status === EnrollmentStatus::Waitlisted;
    }

    public function isPending(): bool
    {
        return $this->status === EnrollmentStatus::Pending;
    }

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Active;
    }

    public function isComplete(): bool
    {
        return $this->status === EnrollmentStatus::Completed;
    }

    /**
     * Whether this enrollment grants access to the learning player — an active or
     * already-completed student (revisiting a finished course is allowed).
     */
    public function grantsLearningAccess(): bool
    {
        return in_array($this->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true);
    }
}
