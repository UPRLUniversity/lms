<?php

namespace App\Services\Commerce;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one place a cart's identity, contents and merging are decided.
 *
 * A signed-out visitor must be able to fill a cart before registering — that is the
 * point of a public catalogue — so a guest cart is keyed on a long random cookie
 * token and folded into the user's cart on login (see merge()).
 *
 * Prices are snapshotted onto items purely so the cart reads consistently between
 * page loads. They are NOT authoritative: CheckoutService re-resolves everything
 * through PricingService before writing an order, so a stale or tampered cart row can
 * never change what someone is charged.
 */
class CartService
{
    public function __construct(private readonly PricingService $pricing) {}

    /*
    |--------------------------------------------------------------------------
    | Resolving the current cart
    |--------------------------------------------------------------------------
    */

    /**
     * The viewer's cart, creating one if needed.
     */
    public function current(?User $user): Cart
    {
        return $user
            ? $this->forUser($user)
            : $this->forGuestToken($this->guestToken());
    }

    /**
     * The viewer's cart if one already exists — never creates. Used on read paths
     * (the header badge, the catalogue) so merely browsing does not write rows.
     */
    public function existing(?User $user): ?Cart
    {
        $cart = $user
            ? Cart::where('user_id', $user->id)->first()
            : (($token = $this->guestToken(false)) ? Cart::where('session_token', $token)->first() : null);

        return $cart?->load('items.course.media');
    }

    public function itemCount(?User $user): int
    {
        return $user
            ? (int) CartItem::whereRelation('cart', 'user_id', $user->id)->count()
            : (($token = $this->guestToken(false))
                ? (int) CartItem::whereRelation('cart', 'session_token', $token)->count()
                : 0);
    }

    private function forUser(User $user): Cart
    {
        $cart = Cart::firstOrCreate(['user_id' => $user->id], ['expires_at' => null]);

        return $cart->load('items.course.programmeParts.programme', 'items.course.media');
    }

    private function forGuestToken(string $token): Cart
    {
        $cart = Cart::firstOrCreate(
            ['session_token' => $token],
            ['expires_at' => now()->addDays((int) config('commerce.cart.guest_lifetime_days', 30))],
        );

        return $cart->load('items.course.programmeParts.programme', 'items.course.media');
    }

    /**
     * The guest's cart token, held in two places on purpose.
     *
     * The SESSION is the primary store: it is already per-visitor, already signed, and
     * it is what survives correctly within a visit. The COOKIE is a longer-lived
     * mirror, so a visitor who comes back next week still finds their basket after the
     * session has expired — a session lasts 120 minutes by default, which is far too
     * short to hold an abandoned cart.
     *
     * The cookie is httpOnly: nothing client-side needs this value, and a token that
     * identifies a cart should not be readable by script.
     */
    private function guestToken(bool $create = true): ?string
    {
        $sessionKey = 'cart.guest_token';
        $name = (string) config('commerce.cart.cookie', 'uprl_cart');

        if (filled($token = session()->get($sessionKey))) {
            return $token;
        }

        // Returning visitor: the session has gone but the cookie has not. Re-seed the
        // session from it so the rest of the request reads one place.
        if (filled($token = request()->cookie($name))) {
            session()->put($sessionKey, $token);

            return $token;
        }

        if (! $create) {
            return null;
        }

        $token = (string) Str::ulid().Str::random(16);

        session()->put($sessionKey, $token);

        Cookie::queue(Cookie::make(
            name: $name,
            value: $token,
            minutes: 60 * 24 * (int) config('commerce.cart.guest_lifetime_days', 30),
            httpOnly: true,
        ));

        return $token;
    }

    /*
    |--------------------------------------------------------------------------
    | Mutating
    |--------------------------------------------------------------------------
    */

    /**
     * Add a course. Idempotent — a second add is a no-op, not a duplicate line,
     * because a course seat has no meaningful quantity.
     *
     * Returns false when the cart is already at its ceiling.
     */
    public function add(Cart $cart, Course $course): bool
    {
        if ($cart->items()->where('course_id', $course->id)->exists()) {
            return true;
        }

        if ($cart->items()->count() >= (int) config('commerce.cart.max_items', 50)) {
            return false;
        }

        $cart->items()->create([
            'course_id' => $course->id,
            'unit_price' => $this->pricing->priceFor($course),
        ]);

        $cart->unsetRelation('items');

        return true;
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        // Belongs-to check so a crafted id cannot empty someone else's cart.
        if ($item->cart_id !== $cart->id) {
            return;
        }

        $item->delete();
        $cart->unsetRelation('items');
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->unsetRelation('items');
    }

    /**
     * Drop courses the buyer already owns or is already enrolled on. Called when the
     * cart is viewed and again at checkout, because either can become true between
     * adding and paying (an admin may enrol them, or another tab may have paid).
     *
     * @return int number of lines removed
     */
    public function pruneUnbuyable(Cart $cart, User $user): int
    {
        $cart->loadMissing('items.course');

        $owned = $this->pricing->purchasedCourseIds($user);

        $remove = $cart->items->filter(function (CartItem $item) use ($owned, $user) {
            if ($item->course === null) {
                return true;    // course deleted underneath the cart
            }

            return in_array($item->course_id, $owned, true)
                || $item->course->enrollmentFor($user)?->grantsLearningAccess();
        });

        if ($remove->isEmpty()) {
            return 0;
        }

        CartItem::whereIn('id', $remove->pluck('id'))->delete();
        $cart->unsetRelation('items');

        return $remove->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Guest → user merge
    |--------------------------------------------------------------------------
    */

    /**
     * Fold the guest cart identified by the request cookie into the user's cart.
     *
     * Runs on the Login event. The guest cart is deleted afterwards and the cookie
     * forgotten, so the same token can never resurrect a cart onto a second account.
     */
    public function merge(User $user): void
    {
        $token = $this->guestToken(false);
        if ($token === null) {
            return;
        }

        $guestCart = Cart::where('session_token', $token)->with('items')->first();

        if ($guestCart === null || $guestCart->items->isEmpty()) {
            $this->forgetGuestCookie();

            return;
        }

        DB::transaction(function () use ($guestCart, $user) {
            $userCart = Cart::firstOrCreate(['user_id' => $user->id]);
            $existing = $userCart->items()->pluck('course_id')->all();

            foreach ($guestCart->items as $item) {
                // The user's own cart wins on collision — their price snapshot is the
                // one they have already seen while signed in.
                if (in_array($item->course_id, $existing, true)) {
                    continue;
                }

                $userCart->items()->create([
                    'course_id' => $item->course_id,
                    'unit_price' => $item->unit_price,
                ]);
            }

            $guestCart->delete();
        });

        $this->forgetGuestCookie();
    }

    /**
     * Drop both halves of the guest identity, so the same token can never resurrect a
     * cart onto a second account.
     */
    private function forgetGuestCookie(): void
    {
        session()->forget('cart.guest_token');
        Cookie::queue(Cookie::forget((string) config('commerce.cart.cookie', 'uprl_cart')));
    }
}
