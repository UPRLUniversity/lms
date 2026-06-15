<?php

namespace App\Enums;

/**
 * Lifecycle of a single student's place in a course. String-backed so the value is
 * exactly what's stored in enrollments.status.
 *
 *   pending     → self-requested on an approval course, awaiting a decision
 *   active      → enrolled and learning
 *   waitlisted  → the course was full; holds a queue position until a seat frees
 *   rejected    → an approval request was declined
 *   completed   → finished the course (kept for history; frees its seat)
 *   withdrawn   → left the course (self or staff); frees its seat
 */
enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending approval',
            self::Active => 'Active',
            self::Waitlisted => 'Waitlisted',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Badge variant used by <x-ui.badge> when rendering this status.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Completed => 'success',
            self::Pending => 'gold',
            self::Waitlisted => 'neutral',
            self::Rejected, self::Withdrawn => 'crimson',
        };
    }

    /**
     * Whether this enrollment occupies one of the course's finite seats. Active and
     * pending students hold a seat (pending is a reserved seat awaiting approval);
     * everything else does not, so capacity is counted from exactly these two.
     */
    public function occupiesSeat(): bool
    {
        return $this === self::Active || $this === self::Pending;
    }

    /**
     * The "live" enrollments a student can hold for a course — used to block a
     * duplicate enrollment while one of these is in force. A withdrawn or rejected
     * record may be re-enrolled over.
     */
    public function isLive(): bool
    {
        return in_array($this, [self::Pending, self::Active, self::Waitlisted, self::Completed], true);
    }

    /**
     * Statuses that occupy a seat, as raw values — for query constraints.
     *
     * @return array<int, string>
     */
    public static function seatHolders(): array
    {
        return [self::Active->value, self::Pending->value];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
