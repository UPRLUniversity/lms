<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Authorization for the enrolment domain. The super-admin short-circuits every check
 * via the Gate::before hook, so these rules govern admins, instructors and auditors.
 *
 * Course-scoped abilities (viewRoster/manageRoster/enrollOthers/approveEnrollments)
 * are registered as named gates in AppServiceProvider, since their subject is a
 * Course (whose own policy is CoursePolicy). The Enrollment-instance abilities
 * (approve/reject/withdraw) resolve here through normal policy auto-discovery.
 */
class EnrollmentPolicy
{
    /**
     * See a course's roster. Admins and the read-only auditor see every roster;
     * instructors see only the courses they teach.
     */
    public function viewRoster(User $user, Course $course): bool
    {
        if (! $user->can(Permission::EnrollmentsView->value)) {
            return false;
        }

        if ($user->hasRole(Role::Admin->value) || $user->hasRole(Role::Auditor->value)) {
            return true;
        }

        return $course->isTaughtBy($user);
    }

    /**
     * Mutate a roster — withdraw a student, or add one directly. Admins on any
     * course, instructors on theirs; never the read-only auditor.
     */
    public function manageRoster(User $user, Course $course): bool
    {
        if (! $user->can(Permission::EnrollmentsView->value) || $user->hasRole(Role::Auditor->value)) {
            return false;
        }

        if ($user->hasRole(Role::Admin->value)) {
            return true;
        }

        return $course->isTaughtBy($user);
    }

    /**
     * Enrol another user into this course directly (the admin/staff enrolment path).
     * Same rule as managing the roster.
     */
    public function enrollOthers(User $user, Course $course): bool
    {
        return $this->manageRoster($user, $course);
    }

    /**
     * Run a course's approval queue. Reserved for admins and the course's LEAD
     * instructor (co-instructors don't decide enrolments).
     */
    public function approveEnrollments(User $user, Course $course): bool
    {
        if (! $user->can(Permission::EnrollmentsApprove->value)) {
            return false;
        }

        if ($user->hasRole(Role::Admin->value)) {
            return true;
        }

        return $course->isLeadInstructor($user);
    }

    /**
     * Approve a specific pending request — the approval right on its course.
     */
    public function approve(User $user, Enrollment $enrollment): bool
    {
        return $this->approveEnrollments($user, $enrollment->course);
    }

    public function reject(User $user, Enrollment $enrollment): bool
    {
        return $this->approveEnrollments($user, $enrollment->course);
    }

    /**
     * Withdraw an enrollment: the student themselves (self-withdraw), or staff who
     * manage the roster.
     */
    public function withdraw(User $user, Enrollment $enrollment): bool
    {
        if ($user->is($enrollment->user)) {
            return true;
        }

        return $this->manageRoster($user, $enrollment->course);
    }
}
