<?php

namespace App\Support;

/**
 * Money formatting for display.
 *
 * The institution bills in a single currency (config/commerce.php); multi-currency
 * is deliberately out of scope. Amounts are handled as float only at this display
 * boundary — they are stored as decimals and totalled in integer minor units.
 */
class Money
{
    /** Fallback when config is unavailable (e.g. a bare unit test). */
    public const SYMBOL = '₦';

    public static function symbol(): string
    {
        return (string) config('commerce.symbol', self::SYMBOL);
    }

    public static function currency(): string
    {
        return (string) config('commerce.currency', 'NGN');
    }

    /**
     * "₦7,000" — whole units, with decimals only when the amount actually has them.
     * A fee schedule reads badly as "₦7,000.00" in a dense table.
     */
    public static function format(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $decimals = self::hasFraction($value) ? 2 : 0;

        return self::symbol().number_format($value, $decimals);
    }

    /**
     * Convert to the integer minor units gateways transact in (kobo for NGN).
     *
     * Rounds rather than truncates, and goes through a string cast first: casting
     * 7000.10 * 100 straight to int yields 700009 on some platforms because the
     * float is really 7000.0999999. Every gateway amount goes through here.
     */
    public static function toMinor(float|int|string|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    public static function fromMinor(int $minor): float
    {
        return $minor / 100;
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
