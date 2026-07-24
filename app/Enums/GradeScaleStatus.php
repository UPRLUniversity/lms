<?php

namespace App\Enums;

/**
 * A grade scale is archived, never deleted, once it has been referenced by a course
 * override or a completion snapshot — archiving just hides it from future selection.
 */
enum GradeScaleStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Archived => 'neutral',
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
