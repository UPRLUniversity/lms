<?php

namespace App\Enums;

enum PaymentEnvironment: string
{
    case Test = 'test';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Test => 'Test',
            self::Live => 'Live',
        };
    }

    /**
     * Live is deliberately the alarming colour: an admin glancing at the Payment
     * methods screen should be able to tell at once that real money is moving.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Test => 'neutral',
            self::Live => 'crimson',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $e) => $e->value, self::cases());
    }
}
