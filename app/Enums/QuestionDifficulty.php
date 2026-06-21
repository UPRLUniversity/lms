<?php

namespace App\Enums;

/**
 * Difficulty band of a question. Drives the bank filter and is a selectable axis in a
 * pooled assessment's selection rules ("5 hard from Category X").
 */
enum QuestionDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Easy',
            self::Medium => 'Medium',
            self::Hard => 'Hard',
        };
    }

    /**
     * Badge variant used by <x-ui.badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Easy => 'success',
            self::Medium => 'gold',
            self::Hard => 'crimson',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $d) => $d->value, self::cases());
    }
}
