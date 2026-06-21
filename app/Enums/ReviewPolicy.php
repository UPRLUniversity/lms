<?php

namespace App\Enums;

/**
 * When a student may review a submitted attempt — their answers against the correct
 * ones, plus explanations. This is the gate that keeps an exam's answer key from
 * leaking while the window is still open.
 *
 *   immediately → review allowed as soon as the attempt is graded
 *   after_close → review only after the assessment's available_until has passed
 *   never       → the student sees their score, never the per-question breakdown
 */
enum ReviewPolicy: string
{
    case Immediately = 'immediately';
    case AfterClose = 'after_close';
    case Never = 'never';

    public function label(): string
    {
        return match ($this) {
            self::Immediately => 'Immediately after submitting',
            self::AfterClose => 'After the assessment closes',
            self::Never => 'Never (score only)',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
