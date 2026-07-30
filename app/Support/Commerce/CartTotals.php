<?php

namespace App\Support\Commerce;

use App\Models\Coupon;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * A priced cart: its lines, the coupon in play, and the arithmetic. Built by
 * CheckoutService::quote() and rendered by the cart and checkout screens, so both show
 * exactly the figures the order will be written with.
 *
 * @property-read Collection<int, PriceLine> $lines
 */
final class CartTotals
{
    /**
     * @param  Collection<int, PriceLine>  $lines
     */
    public function __construct(
        public readonly Collection $lines,
        public readonly float $subtotal,
        public readonly float $discount,
        public readonly float $total,
        public readonly ?Coupon $coupon = null,
        public readonly ?string $couponError = null,
    ) {}

    /**
     * @param  Collection<int, PriceLine>  $lines
     */
    public static function from(Collection $lines, float $discount = 0.0, ?Coupon $coupon = null, ?string $couponError = null): self
    {
        $subtotal = round($lines->sum(fn (PriceLine $line) => $line->amount), 2);
        $discount = round(min(max($discount, 0), $subtotal), 2);

        return new self(
            lines: $lines,
            subtotal: $subtotal,
            discount: $discount,
            total: round($subtotal - $discount, 2),
            coupon: $coupon,
            couponError: $couponError,
        );
    }

    /**
     * Course lines — what the buyer actually chose.
     *
     * @return Collection<int, PriceLine>
     */
    public function courseLines(): Collection
    {
        return $this->lines->reject(fn (PriceLine $line) => $line->isEntryFee())->values();
    }

    /**
     * Programme entry fees, grouped for display so the cart can explain in one place
     * why a one-off ₦45,000 appeared alongside a ₦7,000 paper.
     *
     * @return Collection<int, PriceLine>
     */
    public function feeLines(): Collection
    {
        return $this->lines->filter(fn (PriceLine $line) => $line->isEntryFee())->values();
    }

    public function isEmpty(): bool
    {
        return $this->lines->isEmpty();
    }

    /**
     * A total of zero still needs "paying" — it goes through checkout so the order,
     * enrolment and receipt all exist — but no gateway is involved.
     */
    public function isFree(): bool
    {
        return $this->total <= 0;
    }

    public function hasDiscount(): bool
    {
        return $this->discount > 0;
    }

    public function formattedSubtotal(): string
    {
        return Money::format($this->subtotal);
    }

    public function formattedDiscount(): string
    {
        return '−'.Money::format($this->discount);
    }

    public function formattedTotal(): string
    {
        return Money::formatOrFree($this->total);
    }
}
