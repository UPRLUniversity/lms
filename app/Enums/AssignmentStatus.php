<?php

namespace App\Enums;

/**
 * Assignment lifecycle. Draft is instructor-only; published assignments appear in the
 * student curriculum and accept submissions. Publishing is gated (max_points required
 * and > 0) so every published assignment is gradebook-safe (Section 6.5).
 */
enum AssignmentStatus: string
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

    /**
     * Badge variant for <x-ui.badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'success',
        };
    }
}
