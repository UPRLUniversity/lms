<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Question;
use App\Models\User;

/**
 * Authorization for the question bank. The super-admin short-circuits via Gate::before.
 * A course-scoped question follows the course's management rule; a global (course-less)
 * question is managed by its creator or an admin.
 */
class QuestionPolicy
{
    public function __construct(private readonly CoursePolicy $courses) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CoursesCreate->value) || $user->hasRole(Role::Auditor->value);
    }

    public function view(User $user, Question $question): bool
    {
        if ($this->canManage($user, $question)) {
            return true;
        }

        return $user->hasRole(Role::Auditor->value) && $user->can(Permission::AssessmentsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CoursesCreate->value);
    }

    public function update(User $user, Question $question): bool
    {
        return $this->canManage($user, $question);
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->canManage($user, $question);
    }

    public function duplicate(User $user, Question $question): bool
    {
        return $this->canManage($user, $question);
    }

    /**
     * Course question → defer to the course rule; global question → creator or admin.
     */
    private function canManage(User $user, Question $question): bool
    {
        if ($question->course) {
            return $this->courses->update($user, $question->course);
        }

        if ($user->hasRole(Role::Admin->value)) {
            return true;
        }

        return $question->created_by !== null && $question->created_by === $user->id;
    }
}
