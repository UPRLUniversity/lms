<?php

namespace App\Enums;

/**
 * The five fixed roles for the single-university LMS. String-backed so the values
 * are exactly the spatie role names stored in the database, while giving the rest
 * of the app type-safe references (seeders, policies, middleware, factories).
 */
enum Role: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Instructor = 'instructor';
    case Student = 'student';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Instructor => 'Instructor',
            self::Student => 'Student',
            self::Auditor => 'Auditor',
        };
    }

    /**
     * Badge variant used by <x-ui.badge> when rendering this role.
     */
    public function badge(): string
    {
        return match ($this) {
            self::SuperAdmin, self::Admin => 'crimson',
            self::Instructor => 'gold',
            self::Auditor => 'neutral',
            self::Student => 'success',
        };
    }

    /**
     * The privileged roles only a super-admin may grant. Guards privilege
     * escalation: an admin can manage people but cannot mint other admins.
     *
     * @return array<int, string>
     */
    public static function adminGranted(): array
    {
        return [self::SuperAdmin->value, self::Admin->value];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
