<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Commerce\CartService;
use Illuminate\Auth\Events\Login;

/**
 * Folds a signed-out visitor's cart into their account the moment they log in.
 *
 * This is what makes the guest journey honest: someone can browse the public
 * catalogue, fill a cart, then register or log in at checkout and still find their
 * courses there. Without it, the "add to cart before signing in" affordance would be
 * a trap.
 *
 * Deliberately synchronous — the very next request is usually the cart or checkout
 * page, so a queued listener would race the redirect and show an empty cart.
 */
class MergeGuestCart
{
    public function __construct(private readonly CartService $carts) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->carts->merge($event->user);
        }
    }
}
