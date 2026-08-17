<?php

namespace App\Services\Courses;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Programme;
use App\Models\User;
use App\Support\Courses\ProgressionImpact;
use Illuminate\Support\Collection;

/**
 * "Who would this rule have blocked?" — computed once, read by two surfaces.
 *
 * Switching a programme to `sequential` never revokes access anybody already has: the gate
 * applies at enrolment time only. But "nothing breaks" is not the same as "nothing
 * changes", and an administrator deserves to see the blast radius on the screen where they
 * make the decision rather than after a student writes in.
 *
 * This changes nothing, ever. It only reads.
 */
class ProgressionAuditService
{
    /**
     * Live enrolments in this programme's courses that the sequential rule would refuse.
     *
     * Evaluated as though the rule were already on, whatever the programme is actually set
     * to — that is the whole point, since the question is worthless once the switch has
     * been flipped.
     */
    public function forProgramme(Programme $programme): ProgressionImpact
    {
        $programme->loadMissing('parts.courses');

        $courseIds = $programme->parts->flatMap(fn ($part) => $part->courses->pluck('id'))->unique();

        if ($courseIds->isEmpty()) {
            return ProgressionImpact::empty();
        }

        // Live enrolments only. A withdrawn or rejected student is not "in" anything, and
        // counting them would inflate the number the admin is trying to judge.
        $enrollments = Enrollment::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Waitlisted->value,
                EnrollmentStatus::Completed->value,
            ])
            ->with(['user', 'course'])
            ->get();

        $rows = new Collection;

        // Grouped by student so each one's passed-course set is resolved once, however many
        // of this programme's courses they are enrolled in.
        foreach ($enrollments->groupBy('user_id') as $forStudent) {
            $student = $forStudent->first()->user;

            if (! $student instanceof User) {
                continue;
            }

            $courses = $forStudent->map(fn (Enrollment $e) => $e->course)->filter()->unique('id')->values();

            // A fresh service per student: the passed-course memo is per-instance and
            // per-user, and an estate-wide audit may walk thousands of students in one run.
            $verdicts = (new ProgressionService)->verdictsFor($student, $courses, $programme);

            foreach ($forStudent as $enrollment) {
                $verdict = $verdicts->get($enrollment->course_id);

                if (! $verdict?->isBlocked()) {
                    continue;
                }

                $rows->push([
                    'student' => $student->name,
                    'email' => $student->email,
                    'course' => $enrollment->course->code,
                    'courseTitle' => $enrollment->course->title,
                    'status' => $enrollment->status->value,
                    'blockedBy' => $verdict->blockingPart?->name,
                    'override' => $enrollment->overrodePrerequisites(),
                ]);
            }
        }

        return new ProgressionImpact(
            checked: $enrollments->count(),
            rows: $rows->sortBy('student')->values(),
        );
    }
}
