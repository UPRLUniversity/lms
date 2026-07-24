<?php

namespace App\Enums;

/**
 * How a grade scale renders its result: the letter/label only, the grade point only,
 * or both together (the default). Purely presentational — never affects computation.
 */
enum GradeDisplayMode: string
{
    case Letter = 'letter';
    case Points = 'points';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Letter => 'Letter only',
            self::Points => 'Points only',
            self::Both => 'Letter + points',
        };
    }

    public function showsLetter(): bool
    {
        return $this !== self::Points;
    }

    public function showsPoints(): bool
    {
        return $this !== self::Letter;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $m) => $m->value, self::cases());
    }
}
