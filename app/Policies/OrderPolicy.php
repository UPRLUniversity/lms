<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;

/**
 * An order is the buyer's financial record. They may always read their own; staff need
 * an explicit permission, because an order carries billing details and a purchase
 * history that is nobody else's business.
 *
 * Nothing may update or delete an order — it is an immutable record of a transaction.
 * Status changes go through OrderFulfilmentService, gated by `manage`.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::OrdersView->value);
    }

    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->can(Permission::OrdersView->value);
    }

    /**
     * Confirming a bank transfer, recording a refund — the actions that move money in
     * the institution's books.
     */
    public function manage(User $user, Order $order): bool
    {
        return $user->can(Permission::OrdersManage->value);
    }
}
