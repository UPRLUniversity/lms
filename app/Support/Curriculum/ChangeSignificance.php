<?php

namespace App\Support\Curriculum;

/**
 * Whether a course edit is worth a student's attention (Section 16).
 *
 * The distinction exists to protect the notification from itself: if every typo fix
 * pinged eighteen people, the bell would be ignored within a week and the one change
 * that mattered — a moved deadline — would be lost in it.
 */
enum ChangeSignificance: string
{
    /**
     * Wording, media, presentation. Recorded in the change history for staff, never
     * announced.
     */
    case Cosmetic = 'cosmetic';

    /**
     * Moves a deadline, changes what is graded, or changes what a learner can reach.
     * Announced to everyone currently taking the course.
     */
    case Material = 'material';

    public function label(): string
    {
        return match ($this) {
            self::Cosmetic => 'Minor edit',
            self::Material => 'Affects students',
        };
    }

    /**
     * Resolved by <x-ui.badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Cosmetic => 'neutral',
            self::Material => 'gold',
        };
    }

    public function isMaterial(): bool
    {
        return $this === self::Material;
    }
}
