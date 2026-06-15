<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Faculty;
use App\Models\User;

/**
 * The academic hierarchy (faculties & departments) is governed by admins. The
 * super-admin bypasses via Gate::before; auditors get read-only viewing.
 */
class FacultyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'auditor']);
    }

    public function view(User $user, Faculty $faculty): bool
    {
        return $user->hasAnyRole(['admin', 'auditor']);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CoursesManage->value) && $user->hasRole('admin');
    }

    public function update(User $user, Faculty $faculty): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Faculty $faculty): bool
    {
        return $this->create($user);
    }
}
