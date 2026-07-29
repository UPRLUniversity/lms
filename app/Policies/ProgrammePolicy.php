<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Programme;
use App\Models\User;

/**
 * The qualification structure (programmes & their parts) is governed by admins —
 * it decides what a course counts toward and what it costs, so an instructor may
 * read it (to place their own course into a part) but never edit it.
 *
 * The super-admin bypasses via Gate::before; auditors get read-only viewing through
 * the ".view" permission.
 */
class ProgrammePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProgrammesView->value);
    }

    public function view(User $user, Programme $programme): bool
    {
        return $user->can(Permission::ProgrammesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ProgrammesManage->value);
    }

    public function update(User $user, Programme $programme): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Programme $programme): bool
    {
        return $this->create($user);
    }
}
