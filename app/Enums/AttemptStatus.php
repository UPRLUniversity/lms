<?php

namespace App\Enums;

/**
 * Lifecycle of a single student attempt.
 *
 *   in_progress → started, not yet submitted (the timer, if any, is running)
 *   submitted   → handed in; objective parts auto-graded, but ≥1 manual item (essay)
 *                 is still awaiting an instructor
 *   graded      → fully scored; final score / pass-fail is settled
 *
 * An attempt with no manual items jumps straight from in_progress to graded on submit.
 */
enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Submitted => 'Awaiting grading',
            self::Graded => 'Graded',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::InProgress => 'gold',
            self::Submitted => 'neutral',
            self::Graded => 'success',
        };
    }

    public function isInProgress(): bool
    {
        return $this === self::InProgress;
    }

    public function isGraded(): bool
    {
        return $this === self::Graded;
    }

    /**
     * Whether the attempt has been handed in (submitted or graded) — i.e. it's no longer
     * editable by the student.
     */
    public function isComplete(): bool
    {
        return $this !== self::InProgress;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
