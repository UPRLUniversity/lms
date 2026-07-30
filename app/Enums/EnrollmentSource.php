<?php

namespace App\Enums;

/**
 * How an enrollment record came to be — for the roster's audit trail and reports.
 * Stored on enrollments.source.
 */
enum EnrollmentSource: string
{
    case Self = 'self';
    case Admin = 'admin';
    case Bulk = 'bulk';
    case Purchase = 'purchase';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'Self-enrolled',
            self::Admin => 'Added by staff',
            self::Bulk => 'Bulk import',
            self::Purchase => 'Purchased',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
