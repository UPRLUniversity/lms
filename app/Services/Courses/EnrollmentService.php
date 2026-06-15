<?php

namespace App\Services\Courses;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Exceptions\EnrollmentException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place enrollment status is decided and written. Every path — self-enrol,
 * waitlist, admin enrol, approve/reject, withdraw, promote — runs through here so the
 * capacity and waitlist invariants hold in a single, queue-safe spot.
 *
 * Capacity safety: any operation that reads-then-writes seat counts does so inside a
 * transaction that first locks the course row (lockForUpdate). That serialises
 * concurrent enrolments/promotions, and the recount-after-lock means a promotion can
 * never hand out more seats than exist (no double-promote under concurrency).
 */
class EnrollmentService
{
    /**
     * A student enrols themselves from the catalogue. Resolves to active (open),
     * pending (approval) or — when the course is full — waitlisted, atomically.
     */
    public function selfEnroll(User $student, Course $course): Enrollment
    {
        return DB::transaction(function () use ($student, $course) {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);

            if (! $course->isPublished()) {
                throw EnrollmentException::notPublished();
            }
            if (! $course->enrollmentMode()->allowsSelfEnrollment()) {
                throw EnrollmentException::inviteOnly();
            }
            if ($course->enrollmentOpensInFuture()) {
                throw EnrollmentException::windowNotOpen();
            }
            if ($course->enrollmentHasClosed()) {
                throw EnrollmentException::windowClosed();
            }

            $existing = $this->lockedEnrollment($student, $course);
            if ($existing && $existing->status->isLive()) {
                throw EnrollmentException::alreadyEnrolled();
            }

            // Full ⇒ waitlist; otherwise the mode's entry status (active/pending).
            $status = $course->isFull()
                ? EnrollmentStatus::Waitlisted
                : $course->enrollmentMode()->entryStatus();

            return $this->writeEnrollment($student, $course, $status, EnrollmentSource::Self);
        });
    }

    /**
     * A staff member enrols a student directly (status active). May exceed capacity by
     * design — a deliberate override of the seat limit. Source is admin for a single
     * enrolment, bulk for a CSV import.
     */
    public function adminEnroll(User $student, Course $course, User $actor, EnrollmentSource $source = EnrollmentSource::Admin): Enrollment
    {
        return DB::transaction(function () use ($student, $course, $actor, $source) {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);

            $existing = $this->lockedEnrollment($student, $course);
            if ($existing && in_array($existing->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true)) {
                throw EnrollmentException::alreadyEnrolled();
            }

            return $this->writeEnrollment(
                $student,
                $course,
                EnrollmentStatus::Active,
                $source,
                approver: $actor,
            );
        });
    }

    /**
     * Approve a pending request → active. (A pending request already holds its seat,
     * so approving never changes the seat count.)
     */
    public function approve(Enrollment $enrollment, User $approver): Enrollment
    {
        if ($enrollment->status !== EnrollmentStatus::Pending) {
            throw ValidationException::withMessages([
                'enrollment' => 'Only a pending request can be approved.',
            ]);
        }

        $enrollment->forceFill([
            'status' => EnrollmentStatus::Active,
            'approved_by' => $approver->id,
            'decision_note' => null,
        ])->save();

        return $enrollment;
    }

    /**
     * Decline a pending request → rejected. Frees the reserved seat, so the waitlist
     * is re-synced.
     */
    public function reject(Enrollment $enrollment, User $approver, ?string $note = null): Enrollment
    {
        if ($enrollment->status !== EnrollmentStatus::Pending) {
            throw ValidationException::withMessages([
                'enrollment' => 'Only a pending request can be rejected.',
            ]);
        }

        $enrollment->forceFill([
            'status' => EnrollmentStatus::Rejected,
            'approved_by' => $approver->id,
            'decision_note' => $note ? trim($note) : null,
        ])->save();

        $this->syncWaitlist($enrollment->course);

        return $enrollment;
    }

    /**
     * A student leaves (or staff withdraws them). If they were occupying a seat, the
     * waitlist is re-synced so the next person is auto-promoted.
     */
    public function withdraw(Enrollment $enrollment): Enrollment
    {
        $freedSeat = $enrollment->status->occupiesSeat();
        $course = $enrollment->course;

        $enrollment->forceFill(['status' => EnrollmentStatus::Withdrawn])->save();

        if ($freedSeat) {
            $this->syncWaitlist($course);
        }

        return $enrollment;
    }

    /**
     * Fill every free seat from the waitlist, earliest first. Idempotent and
     * queue-safe: it locks the course, recounts seats, and promotes at most the number
     * of available seats — so calling it twice (e.g. two racing triggers) never
     * double-promotes. Promotes to active (open) or pending (approval).
     *
     * @return int how many students were promoted
     */
    public function syncWaitlist(Course $course): int
    {
        return DB::transaction(function () use ($course) {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);

            // Uncapped courses can't have a meaningful waitlist; if one exists (e.g.
            // capacity was cleared), promote everyone.
            if (! $course->hasCapacityLimit()) {
                $available = PHP_INT_MAX;
            } else {
                $taken = $course->enrollments()->occupyingSeat()->count();
                $available = (int) $course->capacity - $taken;
            }

            if ($available <= 0) {
                return 0;
            }

            $waitlisted = $course->enrollments()
                ->waitlistOrder()
                ->limit($available === PHP_INT_MAX ? PHP_INT_MAX : $available)
                ->lockForUpdate()
                ->get();

            $promoteTo = $course->enrollmentMode()->entryStatus();

            foreach ($waitlisted as $enrollment) {
                $enrollment->forceFill(['status' => $promoteTo])->save();
            }

            return $waitlisted->count();
        });
    }

    /**
     * Re-evaluate the waitlist after a capacity change (e.g. an instructor raised the
     * cap). Thin alias kept for intent at the call site.
     */
    public function capacityChanged(Course $course): int
    {
        return $this->syncWaitlist($course);
    }

    /**
     * Create or revive the single (user, course) enrollment row. Reusing the existing
     * row honours the unique constraint when a withdrawn/rejected student returns.
     */
    private function writeEnrollment(
        User $student,
        Course $course,
        EnrollmentStatus $status,
        EnrollmentSource $source,
        ?User $approver = null,
    ): Enrollment {
        return Enrollment::updateOrCreate(
            ['user_id' => $student->id, 'course_id' => $course->id],
            [
                'status' => $status,
                'source' => $source,
                'enrolled_at' => now(),
                'approved_by' => $approver?->id,
                'decision_note' => null,
            ],
        );
    }

    /**
     * Fetch + row-lock the student's enrollment for this course inside the open
     * transaction, so the duplicate-enrolment check and the write are atomic.
     */
    private function lockedEnrollment(User $student, Course $course): ?Enrollment
    {
        return Enrollment::query()
            ->where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->lockForUpdate()
            ->first();
    }
}
