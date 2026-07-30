<?php

namespace App\Services\Commerce;

use App\Exceptions\CouponException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Support\Commerce\PriceLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The one place a coupon is validated, applied and redeemed.
 *
 * Two rules worth stating up front, because both are easy to get wrong and expensive
 * when wrong:
 *
 *  1. Entry fees are never discounted. Registration and administration are the
 *     Institute's charge for entering a programme, not a course price, so a "20% off"
 *     code discounts the papers and leaves the entry fees alone.
 *
 *  2. A coupon is REDEEMED only when an order is paid, never when it is applied. A
 *     code held on an abandoned or failed order must not burn its allowance, and a
 *     replayed webhook must not burn it twice — hence redeem() is idempotent and the
 *     ledger has a unique (coupon_id, order_id).
 */
class CouponService
{
    /**
     * Look up a code and check everything that does not depend on the cart.
     *
     * @throws CouponException
     */
    public function find(string $code, ?User $user = null): Coupon
    {
        $coupon = Coupon::query()
            ->where('code', $this->normalise($code))
            ->with(['course', 'programme'])
            ->first();

        // An unknown code and a deactivated one answer identically, so probing cannot
        // confirm that a code exists.
        if ($coupon === null || ! $coupon->is_active) {
            throw CouponException::unknown();
        }

        if (! $coupon->hasStarted()) {
            throw CouponException::notStarted();
        }

        if ($coupon->hasExpired()) {
            throw CouponException::expired();
        }

        if ($coupon->isExhausted()) {
            throw CouponException::exhausted();
        }

        if ($user && $coupon->per_user_limit > 0 && $coupon->redemptionsBy($user) >= $coupon->per_user_limit) {
            throw CouponException::alreadyUsed();
        }

        return $coupon;
    }

    /**
     * Validate a code against a concrete set of price lines.
     *
     * @param  Collection<int, PriceLine>  $lines
     *
     * @throws CouponException
     */
    public function validate(string $code, Collection $lines, ?User $user = null): Coupon
    {
        $coupon = $this->find($code, $user);

        if ($lines->isEmpty()) {
            throw CouponException::emptyCart();
        }

        if ($this->eligibleAmount($coupon, $lines) <= 0) {
            throw CouponException::notApplicable();
        }

        return $coupon;
    }

    /**
     * The portion of the lines this coupon may act on: paid COURSE lines within its
     * scope. Entry fees and free courses are excluded.
     *
     * @param  Collection<int, PriceLine>  $lines
     */
    public function eligibleAmount(Coupon $coupon, Collection $lines): float
    {
        return round($this->eligibleLines($coupon, $lines)->sum(fn (PriceLine $line) => $line->amount), 2);
    }

    /**
     * @param  Collection<int, PriceLine>  $lines
     * @return Collection<int, PriceLine>
     */
    public function eligibleLines(Coupon $coupon, Collection $lines): Collection
    {
        return $lines
            ->reject(fn (PriceLine $line) => $line->isEntryFee() || $line->isFree())
            ->filter(fn (PriceLine $line) => $line->course && $coupon->coversCourse($line->course))
            ->values();
    }

    /**
     * The discount this coupon takes off these lines. Never exceeds the eligible
     * amount, so a total can never go negative.
     *
     * @param  Collection<int, PriceLine>  $lines
     */
    public function discountFor(Coupon $coupon, Collection $lines): float
    {
        return $coupon->type->discountOn(
            $this->eligibleAmount($coupon, $lines),
            (float) $coupon->value,
        );
    }

    /**
     * Record that a coupon was used on a paid order.
     *
     * Idempotent by construction: the unique (coupon_id, order_id) means a replayed
     * webhook finds the existing row rather than adding a second. Called only from
     * OrderFulfilmentService, inside its transaction.
     */
    public function redeem(Order $order): void
    {
        if ($order->coupon_id === null || (float) $order->discount_total <= 0) {
            return;
        }

        $order->redemptions()->firstOrCreate(
            ['coupon_id' => $order->coupon_id],
            ['user_id' => $order->user_id, 'discount_amount' => $order->discount_total],
        );
    }

    public function normalise(string $code): string
    {
        return Str::upper(trim($code));
    }
}
