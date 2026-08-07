<?php

namespace App\Support\Curriculum;

/**
 * The curriculum a student was actually measured against, frozen at the moment their
 * enrollment flipped to Completed (Section 16).
 *
 * Two distinct item sets, because completion and grading never had the same rule:
 *
 *   - required*  — what "100% complete" meant: every published, required item.
 *   - graded*    — what the transcript percentage was computed from: the required items
 *                  that also count toward the grade.
 *
 * Storing both means each consumer reads its own list rather than re-deriving the other's
 * filter, which is precisely the drift this whole mechanism exists to stop.
 */
final readonly class CompletionSnapshot
{
    public const VERSION = 1;

    /**
     * @param  array<int, int>  $lessonIds
     * @param  array<int, int>  $requiredAssessmentIds
     * @param  array<int, int>  $requiredAssignmentIds
     * @param  array<int, int>  $gradedAssessmentIds
     * @param  array<int, int>  $gradedAssignmentIds
     */
    public function __construct(
        public array $lessonIds = [],
        public array $requiredAssessmentIds = [],
        public array $requiredAssignmentIds = [],
        public array $gradedAssessmentIds = [],
        public array $gradedAssignmentIds = [],
        public ?string $capturedAt = null,
        public bool $backfilled = false,
    ) {}

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function fromArray(?array $stored): ?self
    {
        if ($stored === null || $stored === []) {
            return null;
        }

        return new self(
            lessonIds: self::ints($stored['lesson_ids'] ?? []),
            requiredAssessmentIds: self::ints($stored['required_assessment_ids'] ?? []),
            requiredAssignmentIds: self::ints($stored['required_assignment_ids'] ?? []),
            gradedAssessmentIds: self::ints($stored['graded_assessment_ids'] ?? []),
            gradedAssignmentIds: self::ints($stored['graded_assignment_ids'] ?? []),
            capturedAt: $stored['captured_at'] ?? null,
            backfilled: (bool) ($stored['backfilled'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'lesson_ids' => $this->lessonIds,
            'required_assessment_ids' => $this->requiredAssessmentIds,
            'required_assignment_ids' => $this->requiredAssignmentIds,
            'graded_assessment_ids' => $this->gradedAssessmentIds,
            'graded_assignment_ids' => $this->gradedAssignmentIds,
            'captured_at' => $this->capturedAt,
            'backfilled' => $this->backfilled,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function ints(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('intval', $value)) : [];
    }
}
