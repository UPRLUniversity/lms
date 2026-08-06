<?php

namespace App\Services\Courses;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Submission;
use App\Support\Curriculum\CurriculumImpact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The single place that answers "what student work hangs off this curriculum item?".
 *
 * Every curriculum item reduces to three id sets — lessons, assessments, assignments —
 * so a module (which owns all three) and a lone lesson run down exactly the same
 * counting path. Guards, the builder's impact strip and the audit trail all read their
 * numbers from here, so they can never disagree.
 */
class CurriculumImpactService
{
    /**
     * Student work attached to $item. Accepts any of the four curriculum types; a module
     * aggregates everything beneath it.
     */
    public function for(Model $item): CurriculumImpact
    {
        [$lessonIds, $assessmentIds, $assignmentIds] = $this->resolve($item);

        return $this->count($lessonIds, $assessmentIds, $assignmentIds);
    }

    /**
     * Refuse a deletion that would destroy academic record.
     *
     * Follows the QuestionBankService::delete() precedent — the rule is domain logic, so
     * the service throws and each controller surfaces it in its own idiom (422 JSON for
     * the builder's async endpoints, a flash for the redirecting ones).
     *
     * @throws \DomainException
     */
    public function assertDeletable(Model $item): CurriculumImpact
    {
        $impact = $this->for($item);

        if ($impact->hasStudentData()) {
            throw new \DomainException($impact->refusalReason($this->label($item)));
        }

        return $impact;
    }

    /**
     * Whether a whole course still holds enrolments. Course deletion is blocked while it
     * does, which is also what keeps the restrictOnDelete backstop from ever firing.
     */
    public function courseHasEnrollments(Course $course): bool
    {
        return $course->enrollments()->exists();
    }

    /**
     * The item's three id sets: [lessonIds, assessmentIds, assignmentIds].
     *
     * @return array{0: array<int, int>, 1: array<int, int>, 2: array<int, int>}
     */
    private function resolve(Model $item): array
    {
        return match (true) {
            $item instanceof Lesson => [[$item->id], [], []],
            $item instanceof Assessment => [[], [$item->id], []],
            $item instanceof Assignment => [[], [], [$item->id]],
            $item instanceof Module => [
                $item->lessons()->pluck('id')->all(),
                $item->assessments()->pluck('id')->all(),
                $item->assignments()->pluck('id')->all(),
            ],
            default => throw new \InvalidArgumentException(
                'Not a curriculum item: '.$item::class,
            ),
        };
    }

    /**
     * @param  array<int, int>  $lessonIds
     * @param  array<int, int>  $assessmentIds
     * @param  array<int, int>  $assignmentIds
     */
    private function count(array $lessonIds, array $assessmentIds, array $assignmentIds): CurriculumImpact
    {
        $progressRows = $lessonIds === [] ? 0
            : LessonProgress::query()->whereIn('lesson_id', $lessonIds)->count();

        $attempts = $assessmentIds === [] ? 0
            : Attempt::query()->whereIn('assessment_id', $assessmentIds)->count();

        $submissions = $assignmentIds === [] ? 0
            : Submission::query()->whereIn('assignment_id', $assignmentIds)->count();

        $grades = $assignmentIds === [] ? 0
            : Grade::query()->whereIn(
                'submission_id',
                Submission::query()->select('id')->whereIn('assignment_id', $assignmentIds),
            )->count();

        return new CurriculumImpact(
            learners: $this->distinctLearners($lessonIds, $assessmentIds, $assignmentIds),
            progressRows: $progressRows,
            attempts: $attempts,
            submissions: $submissions,
            grades: $grades,
        );
    }

    /**
     * How many distinct students appear across all three sources — one UNION rather than
     * pulling id lists into PHP, so a busy course doesn't hydrate thousands of rows just
     * to render a warning.
     *
     * @param  array<int, int>  $lessonIds
     * @param  array<int, int>  $assessmentIds
     * @param  array<int, int>  $assignmentIds
     */
    private function distinctLearners(array $lessonIds, array $assessmentIds, array $assignmentIds): int
    {
        /** @var array<int, QueryBuilder> $sources */
        $sources = [];

        if ($lessonIds !== []) {
            $sources[] = DB::table('lesson_progress')->select('user_id')->whereIn('lesson_id', $lessonIds);
        }

        if ($assessmentIds !== []) {
            $sources[] = DB::table('attempts')->select('user_id')->whereIn('assessment_id', $assessmentIds);
        }

        if ($assignmentIds !== []) {
            $sources[] = DB::table('submissions')->select('user_id')->whereIn('assignment_id', $assignmentIds);
        }

        if ($sources === []) {
            return 0;
        }

        $union = array_shift($sources);

        foreach ($sources as $source) {
            $union->union($source);
        }

        return DB::query()->fromSub($union, 'learners')->distinct()->count('user_id');
    }

    /**
     * The noun used in refusal copy, so the message reads naturally per type.
     */
    private function label(Model $item): string
    {
        return match (true) {
            $item instanceof Lesson => 'lesson',
            $item instanceof Assessment => 'assessment',
            $item instanceof Assignment => 'assignment',
            $item instanceof Module => 'module',
            default => 'item',
        };
    }
}
