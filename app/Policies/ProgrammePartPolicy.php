<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProgrammePart;
use App\Models\User;

/**
 * Parts inherit their programme's governance — see ProgrammePolicy. Kept as its own
 * policy so Laravel's auto-discovery resolves ProgrammePart without falling back to
 * a gate, and so parts can diverge later without unpicking the programme rules.
 */
class ProgrammePartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProgrammesView->value);
    }

    public function view(User $user, ProgrammePart $part): bool
    {
        return $user->can(Permission::ProgrammesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ProgrammesManage->value);
    }

    public function update(User $user, ProgrammePart $part): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, ProgrammePart $part): bool
    {
        return $this->create($user);
    }
}
