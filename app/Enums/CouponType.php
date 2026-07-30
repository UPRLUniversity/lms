<?php

namespace App\Enums;

use App\Support\Money;

/**
 * How a coupon reduces the eligible subtotal.
 *
 * Full is not "100 percent" — it is its own case so a free-access code can never be
 * defeated by rounding, and so the UI can say "Free" rather than "100% off".
 */
enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage off',
            self::Fixed => 'Fixed amount off',
            self::Full => 'Free (100% off)',
        };
    }

    /**
     * Human summary of the coupon's effect, e.g. "20% off" or "₦5,000 off".
     */
    public function describe(float $value): string
    {
        return match ($this) {
            self::Percentage => rtrim(rtrim(number_format($value, 2), '0'), '.').'% off',
            self::Fixed => Money::format($value).' off',
            self::Full => 'Free',
        };
    }

    /**
     * The discount this coupon takes off an eligible amount.
     *
     * Never returns more than the eligible amount — a ₦5,000 fixed coupon against a
     * ₦3,000 cart discounts ₦3,000, not ₦5,000, so a total can never go negative and
     * the institution never owes the student money.
     */
    public function discountOn(float $eligible, float $value): float
    {
        $discount = match ($this) {
            self::Percentage => $eligible * (min(max($value, 0), 100) / 100),
            self::Fixed => $value,
            self::Full => $eligible,
        };

        return round(min(max($discount, 0), $eligible), 2);
    }

    /**
     * Whether `value` is meaningful for this type (Full ignores it).
     */
    public function usesValue(): bool
    {
        return $this !== self::Full;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
