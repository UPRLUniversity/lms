<?php

namespace App\Services\Assignments;

use App\Enums\SubmissionStatus;
use App\Models\Grade;
use App\Models\RubricCriterion;
use App\Models\Submission;
use App\Models\User;
use App\Services\Courses\LearningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rubric-based grading. The server is the only scorer: points come from the rubric's
 * level definitions (or, rubric-free, a capped direct score) — whatever total the
 * client displayed is ignored. Grading/regrading/returning always re-syncs the
 * student's course progress, since graded required assignments count toward it.
 */
class AssignmentGradingService
{
    public function __construct(private readonly LearningService $learning) {}

    /**
     * Grade (or regrade) a submission.
     *
     * With a rubric: $input['criteria'] maps criterion id → chosen level index; every
     * criterion must be scored; points_total is recomputed from the rubric here.
     * Without: $input['points'] is a direct score, capped at the assignment's max_points.
     *
     * @param  array{criteria?: array<int|string, int|string>, points?: float|int|string, feedback?: string|null}  $input
     */
    public function grade(Submission $submission, User $grader, array $input): Grade
    {
        $assignment = $submission->assignment;
        $rubric = $assignment->rubric?->load('criteria');

        if ($rubric && $rubric->criteria->isNotEmpty()) {
            [$scores, $total] = $this->scoreAgainstRubric($rubric->criteria, $input['criteria'] ?? []);
        } else {
            $scores = null;
            $max = (float) ($assignment->max_points ?? 0);
            $total = min(max((float) ($input['points'] ?? 0), 0), $max);
        }

        return DB::transaction(function () use ($submission, $grader, $scores, $total, $input) {
            $grade = Grade::updateOrCreate(
                ['submission_id' => $submission->id],
                [
                    'grader_id' => $grader->id,
                    'criterion_scores' => $scores,
                    'points_total' => $total,
                    'feedback' => $input['feedback'] ?? null,
                    'graded_at' => now(),
                ],
            );

            $submission->forceFill(['status' => SubmissionStatus::Graded->value])->save();

            $this->syncProgress($submission);

            return $grade;
        });
    }

    /**
     * Send a submission back to the student for another version. The note is required —
     * a student must always know WHY it came back. Any existing grade stays attached to
     * this version for the audit trail, but the version stops counting as complete.
     */
    public function returnForResubmission(Submission $submission, User $returner, string $note): Submission
    {
        if (trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => 'Add a note telling the student what to improve before returning their work.',
            ]);
        }

        $submission->forceFill([
            'status' => SubmissionStatus::ReturnedForResubmission->value,
            'return_note' => $note,
            'returned_by' => $returner->id,
            'returned_at' => now(),
        ])->save();

        $this->syncProgress($submission);

        return $submission;
    }

    /**
     * Recompute chosen levels into a snapshot + total, trusting only the rubric.
     *
     * @param  \Illuminate\Support\Collection<int, RubricCriterion>  $criteria
     * @param  array<int|string, int|string>  $selections  criterion id → level index
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     *
     * @throws ValidationException when any criterion is missing or a level is invalid
     */
    private function scoreAgainstRubric($criteria, array $selections): array
    {
        $scores = [];
        $total = 0.0;

        foreach ($criteria as $criterion) {
            if (! array_key_exists($criterion->id, $selections) && ! array_key_exists((string) $criterion->id, $selections)) {
                throw ValidationException::withMessages([
                    'criteria' => "Choose a level for every criterion — “{$criterion->title}” hasn't been scored yet.",
                ]);
            }

            $index = (int) ($selections[$criterion->id] ?? $selections[(string) $criterion->id]);
            $level = $criterion->level($index);

            if ($level === null) {
                throw ValidationException::withMessages([
                    'criteria' => "The level chosen for “{$criterion->title}” doesn't exist on this rubric.",
                ]);
            }

            $points = (float) ($level['points'] ?? 0);
            $total += $points;

            $scores[] = [
                'criterion_id' => $criterion->id,
                'criterion_title' => $criterion->title,
                'level_index' => $index,
                'level_label' => (string) ($level['label'] ?? ''),
                'points' => $points,
                'max_points' => $criterion->maxPoints(),
            ];
        }

        return [$scores, $total];
    }

    /**
     * Keep the student's course % (and Completed flip) in sync after any change to
     * whether this assignment counts as done.
     */
    private function syncProgress(Submission $submission): void
    {
        $assignment = $submission->assignment;

        if ($assignment->is_required && $assignment->isPublished()) {
            $this->learning->recalculate($submission->user, $assignment->course);
        }
    }
}
