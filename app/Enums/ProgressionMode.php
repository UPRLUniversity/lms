<?php

namespace App\Enums;

/**
 * How a learner may move through a course's curriculum. Stored on
 * courses.progression_mode.
 *
 *   free       → every lesson is open; learners choose their own path
 *   sequential → a lesson stays locked until the previous one is completed
 */
enum ProgressionMode: string
{
    case Free = 'free';
    case Sequential = 'sequential';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free — learn in any order',
            self::Sequential => 'Sequential — unlock lessons in order',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Free => 'Learners can jump to any lesson at any time.',
            self::Sequential => 'Each lesson unlocks only once the previous one is completed.',
        };
    }

    public function isSequential(): bool
    {
        return $this === self::Sequential;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $m) => $m->value, self::cases());
    }
}
