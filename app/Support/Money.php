<?php

namespace App\Support;

/**
 * Money formatting for display.
 *
 * Introduced in Section 11 because programme fees have to render in the admin
 * screens the moment they exist. Section 12 (commerce) makes the currency and
 * symbol configurable; until then the institution is Nigerian and prices in naira,
 * so that is the single default here rather than a config lookup that would have
 * nothing behind it.
 *
 * Amounts are handled as float only at the display boundary — they are stored and
 * calculated as decimals.
 */
class Money
{
    public const SYMBOL = '₦';

    /**
     * "₦7,000" — whole naira, with decimals only when the amount actually has them.
     * A fee schedule reads badly as "₦7,000.00" in a dense table.
     */
    public static function format(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $decimals = self::hasFraction($value) ? 2 : 0;

        return self::SYMBOL.number_format($value, $decimals);
    }

    /**
     * Same, but renders zero as a word so a free item reads as free rather than "₦0".
     */
    public static function formatOrFree(float|int|string|null $amount, string $free = 'Free'): string
    {
        return (float) ($amount ?? 0) <= 0 ? $free : self::format($amount);
    }

    private static function hasFraction(float $value): bool
    {
        return abs($value - round($value)) > 0.0001;
    }
}
