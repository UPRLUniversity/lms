<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Authorization for the gradebook. Course-scoped abilities are registered as named
 * gates in AppServiceProvider (subject is a Course, whose own policy is CoursePolicy),
 * mirroring EnrollmentPolicy's viewRoster/manageRoster pattern.
 */
class GradebookPolicy
{
    /**
     * The instructor gradebook matrix. Admins and the read-only auditor see every
     * course; instructors see only the courses they teach.
     */
    public function viewMatrix(User $user, Course $course): bool
    {
        if (! $user->can(Permission::GradebookView->value)) {
            return false;
        }

        if ($user->hasRole(Role::Admin->value) || $user->hasRole(Role::Auditor->value)) {
            return true;
        }

        return $course->isTaughtBy($user);
    }

    /**
     * A student's own "Grades" tab: an enrolled (or completed) student, or staff/auditor
     * previewing read-only — the same rule as opening the player (LessonPolicy@learn).
     */
    public function viewOwn(User $user, Course $course): bool
    {
        $enrollment = $course->enrollmentFor($user);
        if ($enrollment !== null && $enrollment->grantsLearningAccess()) {
            return true;
        }

        return Gate::forUser($user)->allows('view', $course);
    }
}
