<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * "May this user START a conversation?" — the subject is the actor (or a Course for a
 * course group), so these register as named gates in AppServiceProvider rather than
 * model-policy methods. Who may talk to whom is business logic in MessagingService
 * (a shared course); these gates only decide the capability.
 */
class MessagingPolicy
{
    /**
     * Reach messaging at all: everyone except the read-only auditor, who observes but
     * never initiates or replies.
     */
    public function use(User $user): bool
    {
        return ! $user->hasRole(Role::Auditor->value);
    }

    /**
     * Create a group conversation (e.g. "message all enrolled") — instructors + admins.
     * Students may only ever hold one-to-one direct conversations.
     */
    public function createGroup(User $user): bool
    {
        return $user->hasAnyRole([
            Role::Instructor->value,
            Role::Admin->value,
            Role::SuperAdmin->value,
        ]);
    }

    /**
     * Message everyone enrolled on a specific course as one group — the same ownership
     * rule as editing the course, so instructors do it for their own courses.
     */
    public function messageCourse(User $user, Course $course): bool
    {
        return Gate::forUser($user)->allows('update', $course);
    }
}
