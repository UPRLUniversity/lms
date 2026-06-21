<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Authorization for the learning player. The super-admin short-circuits via the
 * Gate::before hook. Two distinct abilities:
 *   - learn → open a lesson in the player (enrolled students, or staff previewing)
 *   - track → write progress for it (enrolled students only; previews are read-only)
 */
class LessonPolicy
{
    /**
     * Open a lesson in the player. An actively-enrolled (or completed) student may
     * learn; staff who can view the course in management may preview it.
     */
    public function learn(User $user, Lesson $lesson): bool
    {
        $course = $lesson->course();

        if (! $course) {
            return false;
        }

        $enrollment = $course->enrollmentFor($user);
        if ($enrollment && $enrollment->grantsLearningAccess()) {
            return true;
        }

        // Staff/auditor preview — same rule as viewing the course in the builder.
        return Gate::forUser($user)->allows('view', $course);
    }

    /**
     * Record progress (mark complete / save a position). Only the enrolled student
     * themselves; a staff preview never mutates a learner's progress.
     */
    public function track(User $user, Lesson $lesson): bool
    {
        $course = $lesson->course();

        if (! $course) {
            return false;
        }

        $enrollment = $course->enrollmentFor($user);

        return $enrollment !== null && $enrollment->grantsLearningAccess();
    }
}
