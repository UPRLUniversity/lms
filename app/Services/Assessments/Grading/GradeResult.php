<?php

namespace App\Services\Assessments\Grading;

/**
 * The outcome of grading one answer. Immutable. `pointsAwarded`/`isCorrect` are null when
 * the answer still needs a human (an essay, or a scenario containing one) — those carry
 * `manual = true` and only `pointsPossible` is known until an instructor grades them.
 */
final class GradeResult
{
    public function __construct(
        public readonly ?float $pointsAwarded,
        public readonly float $pointsPossible,
        public readonly ?bool $isCorrect,
        public readonly bool $manual = false,
    ) {}

    public static function auto(float $awarded, float $possible, ?bool $correct = null): self
    {
        return new self(
            pointsAwarded: round($awarded, 2),
            pointsPossible: round($possible, 2),
            isCorrect: $correct ?? ($possible > 0 && $awarded >= $possible),
            manual: false,
        );
    }

    /**
     * A fully-correct / fully-wrong objective result worth all-or-nothing.
     */
    public static function allOrNothing(bool $correct, float $possible): self
    {
        return self::auto($correct ? $possible : 0.0, $possible, $correct);
    }

    public static function manual(float $possible): self
    {
        return new self(
            pointsAwarded: null,
            pointsPossible: round($possible, 2),
            isCorrect: null,
            manual: true,
        );
    }
}
