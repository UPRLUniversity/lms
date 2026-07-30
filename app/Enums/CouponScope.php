<?php

namespace App\Enums;

/**
 * Which cart lines a coupon is allowed to discount.
 *
 * Scope is also the authorization boundary: an instructor may issue Course coupons
 * for courses they teach, while Global and Programme scopes are admin-only because
 * they cost the institution money across a whole catalogue.
 *
 * Entry fees (registration/administration) are never discounted by any scope — they
 * are the Institute's charge, not the course's.
 */
enum CouponScope: string
{
    case Global = 'global';
    case Course = 'course';
    case Programme = 'programme';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Any course',
            self::Course => 'One course',
            self::Programme => 'One programme',
        };
    }

    /**
     * Whether only an admin may create a coupon at this scope.
     */
    public function isAdminOnly(): bool
    {
        return $this !== self::Course;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
