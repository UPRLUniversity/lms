<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Course-scoped (subject is a Course, not a CourseAnnouncement instance), registered
 * as named gates in AppServiceProvider — same pattern as EnrollmentPolicy's
 * viewRoster/manageRoster.
 */
class CourseAnnouncementPolicy
{
    /**
     * Read the course's announcements. Same rule as opening a lesson in the player:
     * an actively-enrolled (or completed) student, or staff previewing/managing it.
     */
    public function view(User $user, Course $course): bool
    {
        $enrollment = $course->enrollmentFor($user);
        if ($enrollment && $enrollment->grantsLearningAccess()) {
            return true;
        }

        return Gate::forUser($user)->allows('view', $course);
    }

    /**
     * Compose/delete announcements — the same ownership rule as editing the course.
     */
    public function manage(User $user, Course $course): bool
    {
        return Gate::forUser($user)->allows('update', $course);
    }
}
