<?php

namespace App\Enums;

/**
 * How students get into a course. Stored on courses.enrollment_mode.
 *
 *   open        → self-enrol straight to active
 *   approval    → self-enrol creates a pending request a staff member decides on
 *   invite-only → no self-enrolment; an admin enrols people directly
 */
enum EnrollmentMode: string
{
    case Open = 'open';
    case Approval = 'approval';
    case InviteOnly = 'invite-only';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open enrolment',
            self::Approval => 'Approval required',
            self::InviteOnly => 'Invitation only',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Open => 'Anyone can enrol and start learning immediately.',
            self::Approval => 'Students request a place; a staff member approves or declines.',
            self::InviteOnly => 'No public enrolment — an admin adds students directly.',
        };
    }

    /**
     * Whether a student may enrol themselves at all (invite-only courses are
     * staff-enrol only).
     */
    public function allowsSelfEnrollment(): bool
    {
        return $this !== self::InviteOnly;
    }

    /**
     * The status a successful self-enrolment lands on for this mode when a seat is
     * available: open → active, approval → pending.
     */
    public function entryStatus(): EnrollmentStatus
    {
        return $this === self::Open ? EnrollmentStatus::Active : EnrollmentStatus::Pending;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $m) => $m->value, self::cases());
    }
}
