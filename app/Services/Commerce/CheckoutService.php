<?php

namespace App\Services\Commerce;

use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Exceptions\CouponException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Support\Commerce\CartTotals;
use App\Support\Commerce\PriceLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a cart into an Order.
 *
 * The security rule this class exists to enforce: EVERY price is re-resolved from
 * PricingService at the moment the order is written. Nothing about the money comes
 * from the request — not the line prices, not the subtotal, not the discount. A cart
 * row is a convenience for display; a posted total is ignored entirely. This is the
 * only reason it is safe for the cart to carry snapshotted prices at all.
 *
 * The order is created Pending. It becomes Paid only through OrderFulfilmentService,
 * which is also the only thing that grants course access.
 */
class CheckoutService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CouponService $coupons,
        private readonly CartService $carts,
    ) {}

    /**
     * Price a cart for display, optionally with a coupon.
     *
     * A bad coupon does NOT throw here — it comes back on the totals as couponError so
     * the cart can show the reason inline and still price everything else. Throwing
     * would mean a mistyped code blanks the page.
     */
    public function quote(Cart $cart, ?User $user, ?string $couponCode = null): CartTotals
    {
        $lines = $this->pricing->linesFor($cart, $user);

        if (blank($couponCode)) {
            return CartTotals::from($lines);
        }

        try {
            $coupon = $this->coupons->validate($couponCode, $lines, $user);

            return CartTotals::from($lines, $this->coupons->discountFor($coupon, $lines), $coupon);
        } catch (CouponException $e) {
            return CartTotals::from($lines, 0.0, null, $e->getMessage());
        }
    }

    /**
     * Write the order.
     *
     * @param  array<string, mixed>  $billing
     *
     * @throws CheckoutException
     */
    public function place(Cart $cart, User $user, string $paymentMethodKey, array $billing = [], ?string $couponCode = null): Order
    {
        // Anything they already own or are enrolled on is dropped before pricing, so a
        // second tab that paid first cannot cause a double charge here.
        $this->carts->pruneUnbuyable($cart, $user);
        $cart->refresh()->load('items.course.programmeParts.programme');

        if ($cart->items->isEmpty()) {
            throw CheckoutException::emptyCart();
        }

        return DB::transaction(function () use ($cart, $user, $paymentMethodKey, $billing, $couponCode) {
            // Re-resolved inside the transaction. Never trust the cart or the request.
            $lines = $this->pricing->linesFor($cart, $user);

            $coupon = null;
            $discount = 0.0;

            if (filled($couponCode)) {
                // Throws here, unlike quote(): at the point money moves, an invalid
                // code must stop the purchase rather than silently charge full price.
                $coupon = $this->coupons->validate($couponCode, $lines, $user);
                $discount = $this->coupons->discountFor($coupon, $lines);
            }

            $totals = CartTotals::from($lines, $discount, $coupon);

            $order = Order::create([
                'reference' => (string) Str::ulid(),
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'subtotal' => $totals->subtotal,
                'discount_total' => $totals->discount,
                'total' => $totals->total,
                'currency' => config('commerce.currency', 'NGN'),
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'payment_method_key' => $paymentMethodKey,
                'billing' => $billing ?: null,
            ]);

            foreach ($totals->lines as $line) {
                $this->writeItem($order, $line);
            }

            return $order;
        });
    }

    private function writeItem(Order $order, PriceLine $line): void
    {
        $order->items()->create([
            'kind' => $line->kind,
            'course_id' => $line->course?->id,
            'programme_id' => $line->programme?->id,
            // Snapshot: a later rename or reprice must not rewrite a past order.
            'title' => $line->title,
            'unit_price' => $line->amount,
            'line_total' => $line->amount,
        ]);
    }
}
