<?php

namespace App\Enums;

/**
 * Who can discover a published course. A course only ever appears in the public
 * catalogue when it is BOTH published and publicly visible; "enrolled-only" courses
 * are reachable by their enrollees but never listed publicly.
 */
enum CourseVisibility: string
{
    case PublicCatalogue = 'public-catalogue';
    case EnrolledOnly = 'enrolled-only';

    public function label(): string
    {
        return match ($this) {
            self::PublicCatalogue => 'Public catalogue',
            self::EnrolledOnly => 'Enrolled only',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::PublicCatalogue => 'Listed on the public /courses catalogue for anyone to discover.',
            self::EnrolledOnly => 'Hidden from the catalogue; only enrolled learners can reach it.',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::PublicCatalogue;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $v) => $v->value, self::cases());
    }
}
