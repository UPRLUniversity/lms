<?php

namespace App\Enums;

/**
 * A single lesson's progress state for one student. String-backed so the value is
 * exactly what's stored in lesson_progress.status.
 *
 *   not_started → no engagement recorded yet (the implicit default)
 *   in_progress → opened / partially watched (a video position has been saved)
 *   completed   → marked done; counts toward course completion
 */
enum LessonProgressStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
        };
    }

    public function isComplete(): bool
    {
        return $this === self::Completed;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
