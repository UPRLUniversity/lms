<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Rubric;
use App\Models\User;

/**
 * Rubrics are personal authoring tools: the owner edits them; admins may step in;
 * the auditor may read. (Attaching one to an assignment is governed by
 * AssignmentPolicy — a rubric itself holds no student data.)
 */
class RubricPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CoursesCreate->value) || $user->hasRole(Role::Auditor->value);
    }

    public function view(User $user, Rubric $rubric): bool
    {
        return $this->manage($user, $rubric)
            || ($user->hasRole(Role::Auditor->value) && $user->can(Permission::AssignmentsView->value));
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CoursesCreate->value);
    }

    public function manage(User $user, Rubric $rubric): bool
    {
        if ($rubric->created_by === $user->id) {
            return $user->can(Permission::CoursesCreate->value);
        }

        return $user->hasRole(Role::Admin->value) && $user->can(Permission::CoursesManage->value);
    }
}
