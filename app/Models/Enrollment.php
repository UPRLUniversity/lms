<?php

namespace App\Models;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
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
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'source',
        'enrolled_at',
        'approved_by',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'source' => EnrollmentSource::class,
            'enrolled_at' => 'datetime',
        ];
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
}
