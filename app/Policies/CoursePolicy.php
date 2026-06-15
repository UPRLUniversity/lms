<?php

namespace App\Policies;

use App\Enums\CourseStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Course;
use App\Models\User;

/**
 * Authorization for course authoring and governance. The super-admin short-circuits
 * every method via the Gate::before hook, so these rules govern:
 *   - admins    → manage every course, and run the publishing workflow
 *   - instructors → manage ONLY courses they create or are assigned to teach
 *   - auditors  → read-only view of every course, never a mutation
 *   - students  → no management access (the catalogue is public and separate)
 */
class CoursePolicy
{
    /**
     * Reach the course-management list. Authors (instructors/admins) and the
     * read-only auditor qualify; students browse via the public catalogue instead.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CoursesCreate->value) || $user->hasRole(Role::Auditor->value);
    }

    /**
     * View a single course in a management/governance context (the builder, the
     * review screen) — distinct from the public catalogue, which needs no policy.
     */
    public function view(User $user, Course $course): bool
    {
        if ($user->can(Permission::CoursesManage->value)) {
            return $this->update($user, $course);
        }

        // Read-only auditor sees every course but can change nothing.
        return $user->hasRole(Role::Auditor->value) && $user->can(Permission::CoursesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CoursesCreate->value);
    }

    /**
     * The core "may edit this course" rule. Admins (CoursesManage + the admin role)
     * manage all; instructors manage only courses they teach.
     */
    public function update(User $user, Course $course): bool
    {
        if (! $user->can(Permission::CoursesManage->value)) {
            return false;
        }

        if ($user->hasRole(Role::Admin->value)) {
            return true;
        }

        return $course->isTaughtBy($user);
    }

    /**
     * Curriculum edits (modules, lessons, reordering) follow the same ownership rule.
     */
    public function manageCurriculum(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    /**
     * Submit a draft for review — an instructor (or admin) on their own course,
     * and only from the draft state.
     */
    public function submitForReview(User $user, Course $course): bool
    {
        return $this->update($user, $course)
            && $course->status === CourseStatus::Draft;
    }

    /**
     * Run the publishing decision (publish / return-to-draft / archive / restore).
     * Reserved for admins — instructors never publish their own work.
     */
    public function review(User $user, Course $course): bool
    {
        return $user->hasRole(Role::Admin->value) && $user->can(Permission::CoursesManage->value);
    }
}
