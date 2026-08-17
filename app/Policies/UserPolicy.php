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

    /**
     * Edit an account.
     *
     * A super-admin's record is out of an ordinary admin's reach entirely, and the
     * reason is the e-mail field rather than the role field. An admin who can change a
     * super-admin's address can point it at their own inbox, send themselves a password
     * reset and inherit the account — `email_verified_at` is not cleared on the way
     * through, so the stolen account arrives already verified. Guarding the role alone
     * would leave that door open.
     */
    public function update(User $user, User $model): bool
    {
        if ($this->outranksActor($user, $model)) {
            return false;
        }

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

        if ($this->outranksActor($user, $model)) {
            return false;
        }

        return $user->can(Permission::UsersManage->value);
    }

    /**
     * Whether $user may change $model's role at all.
     *
     * Distinct from grantRole(), and the distinction is the whole point. grantRole()
     * asks "may you hand out this role?"; this asks "may you take away what they
     * currently hold?". Asking only the first meant an admin could demote a super-admin
     * to student — student being a role an admin may perfectly well hand out — and so
     * strip a privilege they were never allowed to grant. Taking a privileged role away
     * now needs the same standing as granting one.
     */
    public function changeRole(User $user, User $model): bool
    {
        // No self-promotion or self-demotion, ever. The controller skips the role sync
        // for self as well; this makes the rule true of the ability itself.
        if ($user->is($model)) {
            return false;
        }

        if (! $user->can(Permission::RolesManage->value)) {
            return false;
        }

        return ! $this->holdsPrivilegedRole($model);
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

    /**
     * Is the target further up the ladder than the person acting on them?
     *
     * Only ever consulted for non-super-admins: a super-admin short-circuits every
     * method through the Gate::before hook long before it reaches here.
     */
    private function outranksActor(User $user, User $model): bool
    {
        return $model->hasRole(Role::SuperAdmin->value) && ! $user->hasRole(Role::SuperAdmin->value);
    }

    /**
     * Holds a role only a super-admin is allowed to hand out (admin or super-admin).
     */
    private function holdsPrivilegedRole(User $model): bool
    {
        return $model->hasAnyRole(Role::adminGranted());
    }
}
