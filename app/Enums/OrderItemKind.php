<?php

namespace App\Enums;

/**
 * What an order line is charging for.
 *
 * The published NIPR schedule prices two different things: a per-paper fee for each
 * course, and one-off Registration + Administration fees a candidate pays once on
 * entering a programme. Entry fees are therefore cart-level lines, not part of any
 * course's price — see PricingService::entryFeeLinesFor().
 */
enum OrderItemKind: string
{
    case Course = 'course';
    case RegistrationFee = 'registration_fee';
    case AdministrationFee = 'administration_fee';

    public function label(): string
    {
        return match ($this) {
            self::Course => 'Course',
            self::RegistrationFee => 'Registration fee',
            self::AdministrationFee => 'Administration fee',
        };
    }

    /**
     * Entry fees are charged once per programme, ever — OrderFulfilmentService and
     * PricingService both key off this rather than testing the cases by hand.
     */
    public function isEntryFee(): bool
    {
        return $this !== self::Course;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $k) => $k->value, self::cases());
    }
}
