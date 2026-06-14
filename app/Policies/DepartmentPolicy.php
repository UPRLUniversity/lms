<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\User;

/**
 * Departments mirror FacultyPolicy: admin-managed, auditor read-only, super-admin
 * bypasses via Gate::before.
 */
class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'auditor']);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasAnyRole(['admin', 'auditor']);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CoursesManage->value) && $user->hasRole('admin');
    }

    public function update(User $user, Department $department): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->create($user);
    }
}
