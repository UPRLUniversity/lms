<?php

namespace App\Enums;

/**
 * Lifecycle of an assessment. Simpler than a course's: an assessment is authored as a
 * draft and, once it passes publish validation, goes live to enrolled students. It can
 * return to draft to be edited (existing attempts are preserved).
 */
enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'success',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
