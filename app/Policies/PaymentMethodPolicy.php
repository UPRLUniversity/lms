<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Gateway credentials are the most sensitive configuration in the app — a live secret
 * key moves real money. Admin-only throughout, and deliberately NOT extended to the
 * auditor: "read-only observer" should not include reading payment secrets.
 */
class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentMethodsManage->value);
    }

    public function view(User $user, PaymentMethod $method): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PaymentMethod $method): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, PaymentMethod $method): bool
    {
        return $this->viewAny($user);
    }
}
