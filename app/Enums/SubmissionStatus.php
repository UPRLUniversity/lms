<?php

namespace App\Enums;

/**
 * State machine for one submission version:
 *
 *   submitted ──grade──▶ graded
 *       │                  │
 *       └──return──▶ returned_for_resubmission ◀──return──┘
 *
 * A return always carries a required note; the student answers it by submitting a NEW
 * version (prior versions are immutable — status is the only field that ever changes).
 */
enum SubmissionStatus: string
{
    case Submitted = 'submitted';
    case Graded = 'graded';
    case ReturnedForResubmission = 'returned_for_resubmission';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Graded => 'Graded',
            self::ReturnedForResubmission => 'Returned for resubmission',
        };
    }

    /**
     * Badge variant for <x-ui.badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Submitted => 'crimson',
            self::Graded => 'success',
            self::ReturnedForResubmission => 'gold',
        };
    }
}
