<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\GradeScale;
use App\Models\User;

/**
 * Scale administration is admin-only (per the section brief, /admin/grade-scales) — no
 * auditor read access, since scale definitions aren't part of its gradebook visibility.
 * The super-admin short-circuits via the Gate::before hook.
 */
class GradeScalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::GradeScalesManage->value);
    }

    public function view(User $user, GradeScale $scale): bool
    {
        return $user->can(Permission::GradeScalesManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::GradeScalesManage->value);
    }

    public function update(User $user, GradeScale $scale): bool
    {
        return $user->can(Permission::GradeScalesManage->value);
    }

    public function manage(User $user, GradeScale $scale): bool
    {
        return $this->update($user, $scale);
    }
}
