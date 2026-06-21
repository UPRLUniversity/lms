<?php

namespace App\Enums;

/**
 * How an assessment chooses the questions a student sees.
 *
 *   fixed   → an explicit, ordered list of questions; every student sees the same set
 *   pooled  → selection rules ("N random from Category X at difficulty Y"); each
 *             attempt draws a fresh random set obeying the rules, frozen per attempt
 */
enum SelectionMode: string
{
    case Fixed = 'fixed';
    case Pooled = 'pooled';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed set',
            self::Pooled => 'Question pool',
        };
    }

    public function isPooled(): bool
    {
        return $this === self::Pooled;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $m) => $m->value, self::cases());
    }
}
