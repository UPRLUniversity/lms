<?php

namespace App\Services\Grades;

use App\Enums\AssessmentStatus;
use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\SubmissionStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\GradeScale;
use App\Models\Submission;
use App\Models\User;
use App\Support\Curriculum\CompletionSnapshot;
use App\Support\Grades\GradebookItem;
use App\Support\Grades\GradebookSummary;
use Illuminate\Support\Collection;

/**
 * Pure aggregation over the Section 5/6 scoring engine — reads Attempt/Grade results and
 * maps them through a GradeScale; never writes a score. Gradebook items are every
 * published, REQUIRED assessment/assignment in the course that also COUNTS TOWARD THE
 * GRADE: required keeps "every item graded" and "course reached 100%" in lock-step
 * (see docs/decisions.md for why optional items are excluded), and counts_toward_grade
 * (Section 14) lets an instructor make a practice quiz compulsory to work through
 * without letting its score into the transcript.
 *
 * Course percentage is POINTS-WEIGHTED — Σ earned ÷ Σ possible across graded items —
 * never the mean of per-item percentages, so a 10-point quiz and a 90-point exam don't
 * carry equal weight.
 */
class GradebookService
{
    /**
     * A student's gradebook items.
     *
     * When $frozen is given (a completed student's snapshot), the items are the ones that
     * counted at the moment they finished — so a course edited afterwards can't restate a
     * grade that was already earned. Without it, the live course is used, which is right
     * for everyone still working through it.
     *
     * @return Collection<int, GradebookItem>
     */
    public function itemsFor(User $user, Course $course, ?CompletionSnapshot $frozen = null): Collection
    {
        [$assessments, $assignments] = $this->requiredItems($course, $frozen);

        return $assessments
            ->map(fn (Assessment $a) => $this->buildAssessmentItem($a, $this->bestAttempt($user, $a)))
            ->concat($assignments->map(fn (Assignment $a) => $this->buildAssignmentItem($a, $this->latestGraded($user, $a))))
            ->values();
    }

    /**
     * Batched variant for the instructor matrix — two queries total (one for every
     * student's best attempts, one for every latest graded submission) instead of a
     * per-student round trip, so the matrix page carries no N+1.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, Collection<int, GradebookItem>> keyed by user id
     */
    public function itemsForMany(Collection $users, Course $course): Collection
    {
        [$assessments, $assignments] = $this->requiredItems($course);
        $userIds = $users->pluck('id');

        $attemptsByKey = $assessments->isEmpty() || $userIds->isEmpty()
            ? collect()
            : Attempt::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('assessment_id', $assessments->pluck('id'))
                ->where('status', AttemptStatus::Graded->value)
                ->orderByDesc('percentage')
                ->get()
                ->groupBy(fn (Attempt $a) => $a->user_id.':'.$a->assessment_id)
                ->map(fn (Collection $group) => $group->first());

        $submissionsByKey = $assignments->isEmpty() || $userIds->isEmpty()
            ? collect()
            : Submission::query()
                ->whereIn('user_id', $userIds)
                ->whereIn('assignment_id', $assignments->pluck('id'))
                ->where('status', SubmissionStatus::Graded->value)
                ->with('grade')
                ->orderByDesc('version')
                ->get()
                ->groupBy(fn (Submission $s) => $s->user_id.':'.$s->assignment_id)
                ->map(fn (Collection $group) => $group->first());

        return $users->mapWithKeys(function (User $user) use ($assessments, $assignments, $attemptsByKey, $submissionsByKey) {
            $items = $assessments
                ->map(fn (Assessment $a) => $this->buildAssessmentItem($a, $attemptsByKey->get($user->id.':'.$a->id)))
                ->concat($assignments->map(
                    fn (Assignment $a) => $this->buildAssignmentItem($a, $submissionsByKey->get($user->id.':'.$a->id))
                ))
                ->values();

            return [$user->id => $items];
        });
    }

    /**
     * The whole-course summary: points-weighted percentage across graded items only,
     * mapped to a band on $scale. `provisional` stays true while any required item is
     * still pending, even though a partial percentage is shown from what IS graded.
     *
     * @param  Collection<int, GradebookItem>|null  $items
     */
    public function summaryFor(User $user, Course $course, GradeScale $scale, ?Collection $items = null, ?CompletionSnapshot $frozen = null): GradebookSummary
    {
        $items ??= $this->itemsFor($user, $course, $frozen);

        return $this->summarize($items, $scale);
    }

    /**
     * @param  Collection<int, GradebookItem>  $items
     */
    public function summarize(Collection $items, GradeScale $scale): GradebookSummary
    {
        $graded = $items->filter(fn (GradebookItem $i) => ! $i->pending)->values();
        $provisional = $items->contains(fn (GradebookItem $i) => $i->pending);

        if ($graded->isEmpty()) {
            return new GradebookSummary($items, $scale, null, null, $provisional);
        }

        $earned = (float) $graded->sum('pointsEarned');
        $possible = (float) $graded->sum('pointsPossible');
        $percent = $possible > 0 ? ($earned / $possible) * 100 : 0.0;

        return new GradebookSummary($items, $scale, $percent, $scale->bandFor($percent), $provisional);
    }

    /**
     * The ids of the items a grade is currently computed from, as [assessments, assignments].
     *
     * Exposed so LearningService can freeze exactly this set into a student's completion
     * snapshot without re-deriving the filter — the two rules drifting apart is precisely
     * what the snapshot exists to prevent.
     *
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    public function gradedItemIds(Course $course): array
    {
        [$assessments, $assignments] = $this->requiredItems($course);

        return [$assessments->pluck('id')->all(), $assignments->pluck('id')->all()];
    }

    /**
     * The items a grade is computed from: published, required, counting toward the grade —
     * and visible. An item hidden from students (or sitting inside a hidden module) has
     * been withdrawn from the course, so it stops counting for everyone still working
     * through it; the attempts and grades already recorded against it survive untouched
     * and stay visible to staff.
     *
     * @return array{0: Collection<int, Assessment>, 1: Collection<int, Assignment>}
     */
    private function requiredItems(Course $course, ?CompletionSnapshot $frozen = null): array
    {
        $course->loadMissing(['assessments', 'assignments', 'modules']);

        // A completed student is measured against the set recorded at completion, exactly
        // as it was — including items since hidden or made optional, and excluding ones
        // added afterwards. Order still comes from the live course so the transcript reads
        // sensibly.
        if ($frozen !== null) {
            return [
                $course->assessments
                    ->whereIn('id', $frozen->gradedAssessmentIds)
                    ->sortBy('position')
                    ->values(),
                $course->assignments
                    ->whereIn('id', $frozen->gradedAssignmentIds)
                    ->sortBy('position')
                    ->values(),
            ];
        }

        $hiddenModules = $course->modules->whereNotNull('hidden_at')->pluck('id')->all();

        $visible = fn (Assessment|Assignment $item): bool => $item->hidden_at === null
            && ! ($item->module_id !== null && \in_array($item->module_id, $hiddenModules, true));

        $assessments = $course->assessments
            ->where('status', AssessmentStatus::Published)
            ->where('is_required', true)
            ->where('counts_toward_grade', true)
            ->filter($visible)
            ->sortBy('position')
            ->values();

        $assignments = $course->assignments
            ->where('status', AssignmentStatus::Published)
            ->where('is_required', true)
            ->where('counts_toward_grade', true)
            ->filter($visible)
            ->sortBy('position')
            ->values();

        return [$assessments, $assignments];
    }

    private function bestAttempt(User $user, Assessment $assessment): ?Attempt
    {
        return $assessment->attempts()
            ->where('user_id', $user->id)
            ->where('status', AttemptStatus::Graded->value)
            ->orderByDesc('percentage')
            ->first();
    }

    private function latestGraded(User $user, Assignment $assignment): ?Submission
    {
        return $assignment->submissions()
            ->where('user_id', $user->id)
            ->where('status', SubmissionStatus::Graded->value)
            ->with('grade')
            ->orderByDesc('version')
            ->first();
    }

    private function buildAssessmentItem(Assessment $assessment, ?Attempt $best): GradebookItem
    {
        if ($best === null) {
            $possible = $assessment->totalPoints();

            return new GradebookItem(
                kind: 'assessment',
                model: $assessment,
                pending: true,
                pointsPossible: $possible !== null ? (float) $possible : null,
            );
        }

        return new GradebookItem(
            kind: 'assessment',
            model: $assessment,
            pending: false,
            pointsEarned: (float) $best->score,
            pointsPossible: (float) $best->max_score,
            percent: (float) $best->percentage,
        );
    }

    private function buildAssignmentItem(Assignment $assignment, ?Submission $graded): GradebookItem
    {
        $possible = (float) ($assignment->max_points ?? 0);

        if ($graded === null || $graded->grade === null) {
            return new GradebookItem('assignment', $assignment, pending: true, pointsPossible: $possible);
        }

        $earned = (float) $graded->grade->points_total;

        return new GradebookItem(
            kind: 'assignment',
            model: $assignment,
            pending: false,
            pointsEarned: $earned,
            pointsPossible: $possible,
            percent: $possible > 0 ? ($earned / $possible) * 100 : 0.0,
        );
    }
}
