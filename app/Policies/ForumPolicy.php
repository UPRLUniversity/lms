<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Course-scoped forum authorization (subject is a Course), registered as named gates
 * in AppServiceProvider — same pattern as EnrollmentPolicy's viewRoster/manageRoster.
 * Three tiers:
 *   access      — read the forum: any course member, plus staff/auditor previewing it.
 *   participate — open threads & reply: members who may write (enrolled students and
 *                 the course's instructors/admins). The read-only auditor never posts.
 *   moderate    — pin/lock/remove/answer-as-staff: the course's instructors + admins.
 *
 * The super-admin short-circuits all of these via the Gate::before hook.
 */
class ForumPolicy
{
    /**
     * Read the course's forum. Same reach as opening a lesson or the announcements
     * page: an enrolled/completed student, or staff/auditor who may view the course.
     */
    public function access(User $user, Course $course): bool
    {
        if ($course->isMember($user)) {
            return true;
        }

        return Gate::forUser($user)->allows('view', $course);
    }

    /**
     * Open a thread or post a reply. Members who can write qualify; the read-only
     * auditor is explicitly excluded even though it can read.
     */
    public function participate(User $user, Course $course): bool
    {
        if ($user->hasRole(Role::Auditor->value)) {
            return false;
        }

        if ($course->isMember($user)) {
            return true;
        }

        // A managing admin who isn't formally a member may still take part.
        return $this->moderate($user, $course);
    }

    /**
     * Moderate the forum (pin, lock, remove posts, accept an answer as staff) — the
     * same ownership rule as editing the course, so instructors moderate their own
     * courses and admins moderate all.
     */
    public function moderate(User $user, Course $course): bool
    {
        return Gate::forUser($user)->allows('update', $course);
    }
}
