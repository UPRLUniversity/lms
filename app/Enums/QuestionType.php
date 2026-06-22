<?php

namespace App\Enums;

/**
 * The seven kinds of question the bank can author. String-backed so the value is
 * exactly what's stored in questions.type. Each type drives its payload shape, its
 * per-type editor in the bank, the grading strategy applied on submit, and whether an
 * attempt containing it can be fully auto-graded.
 *
 *   mcq_single  → radio options; exactly one is_correct
 *   mcq_multi   → checkbox options; one-or-more is_correct (all-or-nothing grading)
 *   true_false  → a two-option mcq_single in disguise
 *   fill_blank  → free text matched against an accepted-answers list
 *   matching    → left/right pairs the student re-pairs (proportional grading)
 *   essay       → free prose, graded by hand
 *   scenario    → a rich stem with nested sub-questions of the above types
 */
enum QuestionType: string
{
    case McqSingle = 'mcq_single';
    case McqMulti = 'mcq_multi';
    case TrueFalse = 'true_false';
    case FillBlank = 'fill_blank';
    case Matching = 'matching';
    case Essay = 'essay';
    case Scenario = 'scenario';

    public function label(): string
    {
        return match ($this) {
            self::McqSingle => 'Multiple choice (single answer)',
            self::McqMulti => 'Multiple choice (multiple answers)',
            self::TrueFalse => 'True / false',
            self::FillBlank => 'Fill in the blank',
            self::Matching => 'Matching',
            self::Essay => 'Essay',
            self::Scenario => 'Scenario',
        };
    }

    /**
     * A short label for chips and tight columns.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::McqSingle => 'MCQ',
            self::McqMulti => 'Multi-select',
            self::TrueFalse => 'True/false',
            self::FillBlank => 'Fill blank',
            self::Matching => 'Matching',
            self::Essay => 'Essay',
            self::Scenario => 'Scenario',
        };
    }

    /**
     * Icon name resolved by <x-ui.icon>.
     */
    public function icon(): string
    {
        return match ($this) {
            self::McqSingle => 'check-circle',
            self::McqMulti => 'check-square',
            self::TrueFalse => 'toggle',
            self::FillBlank => 'pencil',
            self::Matching => 'arrows-right-left',
            self::Essay => 'document-text',
            self::Scenario => 'list',
        };
    }

    /**
     * Whether this type is scored entirely by the machine on submit. Essay is the only
     * inherently manual type; a scenario is auto-graded only when none of its
     * sub-questions are essays (decided per-question by Question::requiresManualGrading).
     */
    public function isAutoGraded(): bool
    {
        return $this !== self::Essay && $this !== self::Scenario;
    }

    /**
     * Types that present a set of selectable options in their payload.
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::McqSingle, self::McqMulti, self::TrueFalse], true);
    }

    public function isScenario(): bool
    {
        return $this === self::Scenario;
    }

    public function isEssay(): bool
    {
        return $this === self::Essay;
    }

    /**
     * The types that may appear as a scenario's sub-questions — every type except
     * scenario itself (no nesting of scenarios).
     *
     * @return array<int, self>
     */
    public static function subQuestionTypes(): array
    {
        return array_values(array_filter(self::cases(), fn (self $t) => $t !== self::Scenario));
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
