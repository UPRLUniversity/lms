<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;

/**
 * Authorization for the user-management area. The super-admin short-circuits every
 * method via the Gate::before hook in AppServiceProvider, so the rules here govern
 * admins (full management), auditors (view only) and everyone else (denied).
 */
class UserPolicy
{
    /**
     * View the user list. Admins and auditors (read-only observers) qualify.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsersManage->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::UsersManage->value);
    }

    /**
     * Activate / deactivate. You can never lock yourself out, and only a
     * super-admin may toggle another super-admin.
     */
    public function setActiveStatus(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        if ($model->hasRole(Role::SuperAdmin->value) && ! $user->hasRole(Role::SuperAdmin->value)) {
            return false;
        }

        return $user->can(Permission::UsersManage->value);
    }

    /**
     * Manage role assignments at all.
     */
    public function assignRoles(User $user): bool
    {
        return $user->can(Permission::RolesManage->value);
    }

    /**
     * Whether $user may grant the specific role named. Admins may hand out
     * instructor/student/auditor; only a super-admin may mint admins or
     * super-admins (privilege-escalation guard). Super-admin is covered by the
     * Gate::before bypass, so this method only ever runs for non-super-admins.
     */
    public function grantRole(User $user, string $role): bool
    {
        if (! $user->can(Permission::RolesManage->value)) {
            return false;
        }

        return ! in_array($role, Role::adminGranted(), true);
    }

    /**
     * Send / manage e-mail invitations.
     */
    public function invite(User $user): bool
    {
        return $user->can(Permission::UsersManage->value);
    }
}
