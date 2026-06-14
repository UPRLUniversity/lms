<?php

namespace App\Enums;

/**
 * Lifecycle of a course through the authoring → review → publishing workflow.
 * String-backed so the value is exactly what's stored in the courses.status column.
 *
 *   draft     → being authored by an instructor (or returned for changes)
 *   review    → submitted, awaiting an admin decision
 *   published → live in the catalogue / available to enrollees
 *   archived  → hidden from the catalogue, but existing enrollees keep access
 */
enum CourseStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Review => 'In review',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /**
     * Badge variant used by <x-ui.badge> when rendering this status.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Review => 'gold',
            self::Published => 'success',
            self::Archived => 'crimson',
        };
    }

    /**
     * Statuses this one may move to. The transition table is the single source of
     * truth the publishing service guards against — no ad-hoc status writes.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Review],
            self::Review => [self::Published, self::Draft],
            self::Published => [self::Archived],
            self::Archived => [self::Published, self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
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
