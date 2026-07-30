<?php

namespace App\Services\Courses;

use App\Enums\AssessmentStatus;
use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\SubmissionStatus;
use App\Events\CourseCompleted;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Submission;
use App\Models\User;
use App\Support\Learning\CourseProgress;
use App\Support\Learning\CurriculumItem;
use App\Support\Learning\CurriculumOutline;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The one place lesson progress is read and written. Marking complete, recording a
 * video position, deriving the course percentage and flipping an enrollment to
 * Completed all run through here, so the completion → % → enrollment chain stays in a
 * single, idempotent spot.
 *
 * Idempotency by construction: completion is a state on a (user, lesson)-unique row,
 * never an increment — so a double "Complete & Continue" (a double-click, a retried
 * request) can only ever leave the same single completed row and the same percentage.
 */
class LearningService
{
    public function __construct(private readonly CurriculumOrderService $order) {}

    /**
     * A query-free snapshot of $user's progress through $course: the ordered lesson
     * sequence + a single progress query. Reuses already-loaded curriculum relations.
     */
    public function snapshot(User $user, Course $course): CourseProgress
    {
        $sequence = $this->sequence($course);

        $progress = $sequence->isEmpty()
            ? new Collection
            : LessonProgress::query()
                ->where('user_id', $user->id)
                ->whereIn('lesson_id', $sequence->pluck('id'))
                ->get()
                ->keyBy('lesson_id');

        // Required, published assessments count toward course completion like lessons.
        $assessments = $this->publishedAssessments($course);
        $required = $assessments->where('is_required', true);
        $passedIds = $this->passedAssessmentIds($user, $assessments->pluck('id'));
        $requiredComplete = $required->filter(fn (Assessment $a) => $passedIds->contains($a->id))->count();

        // As do required, published assignments once graded (Section 6).
        $assignments = $this->publishedAssignments($course);
        $requiredAssignments = $assignments->where('is_required', true);
        $gradedIds = $this->gradedAssignmentIds($user, $assignments->pluck('id'));
        $requiredAssignmentsComplete = $requiredAssignments
            ->filter(fn (Assignment $a) => $gradedIds->contains($a->id))
            ->count();

        return new CourseProgress(
            $course,
            $sequence,
            $progress,
            $required->count(),
            $requiredComplete,
            $requiredAssignments->count(),
            $requiredAssignmentsComplete,
        );
    }

    /**
     * The unified learning outline — lessons, published assessments and published
     * assignments interleaved in the ONE merged order the builder wrote (Section 14),
     * with per-item completion and sequential lock state. This is what the player
     * sidebar renders and the gate consults, so a learner always sees exactly the
     * sequence the instructor dragged into place.
     */
    public function outline(User $user, Course $course, ?CourseProgress $snapshot = null): CurriculumOutline
    {
        $snapshot ??= $this->snapshot($user, $course);
        $sequential = $course->isSequential();

        $course->loadMissing([
            'modules' => fn ($q) => $q->orderBy('position'),
            'modules.lessons' => fn ($q) => $q->orderBy('position'),
        ]);

        $assessments = $this->publishedAssessments($course);
        $passedIds = $this->passedAssessmentIds($user, $assessments->pluck('id'));
        $assessmentStatuses = $this->assessmentStatuses($user, $assessments);

        $assignments = $this->publishedAssignments($course);
        $gradedIds = $this->gradedAssignmentIds($user, $assignments->pluck('id'));
        $assignmentStatuses = $this->assignmentStatuses($user, $assignments);

        // Build the flat ordered rows first, then a single pass computes the lock frontier.
        $rows = new Collection;

        $toRow = fn (array $item, ?int $moduleId): array => match ($item['type']) {
            'lesson' => [
                'kind' => 'lesson',
                'model' => $item['model'],
                'completed' => $snapshot->isComplete($item['model']),
                'required' => true,
                'module_id' => $moduleId,
                'placement' => null,
            ],
            'assessment' => [
                'kind' => 'assessment',
                'model' => $item['model'],
                'completed' => $passedIds->contains($item['model']->id),
                'required' => (bool) $item['model']->is_required,
                'module_id' => $moduleId,
                'placement' => $item['model']->placement->value,
                'status' => $assessmentStatuses->get($item['model']->id),
            ],
            'assignment' => [
                'kind' => 'assignment',
                'model' => $item['model'],
                'completed' => $gradedIds->contains($item['model']->id),
                'required' => (bool) $item['model']->is_required,
                'module_id' => $moduleId,
                'placement' => null,
                'status' => $assignmentStatuses->get($item['model']->id),
            ],
        };

        // One merged ladder per bucket — the same merge the builder outline renders.
        foreach ($course->modules as $module) {
            $this->order->merge(
                $module->lessons,
                $assessments->where('module_id', $module->id),
                $assignments->where('module_id', $module->id),
            )->each(fn (array $item) => $rows->push($toRow($item, $module->id)));
        }

        // The course-level bucket closes the outline.
        $this->order->merge(
            [],
            $assessments->whereNull('module_id'),
            $assignments->whereNull('module_id'),
        )->each(fn (array $item) => $rows->push($toRow($item, null)));

        $blocked = false;
        $items = $rows->map(function (array $row) use (&$blocked, $sequential) {
            $locked = $sequential && $blocked;

            // A required, incomplete item closes the gate for everything after it.
            if ($row['required'] && ! $row['completed']) {
                $blocked = true;
            }

            return new CurriculumItem(
                kind: $row['kind'],
                model: $row['model'],
                completed: $row['completed'],
                locked: $locked,
                required: $row['required'],
                moduleId: $row['module_id'],
                placement: $row['placement'],
                statusLabel: $row['status']['label'] ?? null,
                statusTone: $row['status']['tone'] ?? null,
            );
        });

        return new CurriculumOutline($items->values());
    }

    /**
     * Published assessments on a course (uses a loaded relation when present).
     *
     * @return Collection<int, Assessment>
     */
    private function publishedAssessments(Course $course): Collection
    {
        if ($course->relationLoaded('assessments')) {
            return $course->assessments
                ->where('status', AssessmentStatus::Published)
                ->values();
        }

        return collect($course->assessments()->published()->get()->all());
    }

    /**
     * Published assignments on a course (uses a loaded relation when present).
     *
     * @return Collection<int, Assignment>
     */
    private function publishedAssignments(Course $course): Collection
    {
        if ($course->relationLoaded('assignments')) {
            return $course->assignments
                ->where('status', AssignmentStatus::Published)
                ->values();
        }

        return collect($course->assignments()->published()->get()->all());
    }

    /**
     * The subset of $assignmentIds the student has a graded submission on — the
     * assignment-completion signal (a version returned for resubmission stops counting).
     *
     * @param  Collection<int, int>  $assignmentIds
     * @return Collection<int, int>
     */
    private function gradedAssignmentIds(User $user, Collection $assignmentIds): Collection
    {
        if ($assignmentIds->isEmpty()) {
            return new Collection;
        }

        return Submission::query()
            ->where('user_id', $user->id)
            ->where('status', SubmissionStatus::Graded->value)
            ->whereIn('assignment_id', $assignmentIds->all())
            ->pluck('assignment_id')
            ->unique()
            ->values();
    }

    /**
     * The subset of $assessmentIds the student has a passing graded attempt on.
     *
     * @param  Collection<int, int>  $assessmentIds
     * @return Collection<int, int>
     */
    private function passedAssessmentIds(User $user, Collection $assessmentIds): Collection
    {
        if ($assessmentIds->isEmpty()) {
            return new Collection;
        }

        return Attempt::query()
            ->where('user_id', $user->id)
            ->where('status', AttemptStatus::Graded->value)
            ->where('passed', true)
            ->whereIn('assessment_id', $assessmentIds->all())
            ->pluck('assessment_id')
            ->unique()
            ->values();
    }

    /**
     * A "where things stand" label + brand tone per assessment, for every assessment the
     * student hasn't passed yet — one query for every assessment's attempts (not one per
     * assessment), so the sidebar carries no N+1. Passed assessments and ones with no
     * attempt at all resolve to null (the checkmark, or the plain default icon, already
     * says enough).
     *
     * @param  Collection<int, Assessment>  $assessments
     * @return Collection<int, array{label: string, tone: string}> keyed by assessment id
     */
    private function assessmentStatuses(User $user, Collection $assessments): Collection
    {
        if ($assessments->isEmpty()) {
            return new Collection;
        }

        $attemptsByAssessment = Attempt::query()
            ->where('user_id', $user->id)
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get()
            ->groupBy('assessment_id');

        return $assessments->mapWithKeys(function (Assessment $a) use ($attemptsByAssessment) {
            $attempts = $attemptsByAssessment->get($a->id, new Collection);

            return [$a->id => $this->statusForAssessment($a, $attempts)];
        })->filter();
    }

    /**
     * @param  Collection<int, Attempt>  $attempts  every attempt this student has on the assessment
     * @return array{label: string, tone: string}|null
     */
    private function statusForAssessment(Assessment $a, Collection $attempts): ?array
    {
        if ($attempts->isEmpty()) {
            return null;
        }

        if ($attempts->contains(fn (Attempt $at) => $at->status === AttemptStatus::InProgress)) {
            return ['label' => 'In progress', 'tone' => 'gold'];
        }

        if ($attempts->contains(fn (Attempt $at) => $at->status === AttemptStatus::Submitted)) {
            return ['label' => 'Awaiting grading', 'tone' => 'gold'];
        }

        $graded = $attempts->where('status', AttemptStatus::Graded);
        $best = $graded->sortByDesc('percentage')->first();

        if ($best === null) {
            return null;
        }

        if ($best->passed) {
            // Completed — the checkmark already covers it, no extra label needed.
            return null;
        }

        $used = $graded->count();
        $left = $a->max_attempts === null ? null : max(0, $a->max_attempts - $used);
        $score = round((float) $best->percentage).'%';

        $tail = match (true) {
            $left === null => 'retake anytime',
            $left > 0 => $left === 1 ? '1 attempt left' : "{$left} attempts left",
            default => 'no attempts left',
        };

        return ['label' => "Not passed · {$score} · {$tail}", 'tone' => 'crimson'];
    }

    /**
     * A "where things stand" label + brand tone per assignment (submitted/awaiting
     * grading, returned for revision) — one query for every assignment's submissions.
     * Graded (completed) assignments and ones with no submission resolve to null.
     *
     * @param  Collection<int, Assignment>  $assignments
     * @return Collection<int, array{label: string, tone: string}> keyed by assignment id
     */
    private function assignmentStatuses(User $user, Collection $assignments): Collection
    {
        if ($assignments->isEmpty()) {
            return new Collection;
        }

        $submissionsByAssignment = Submission::query()
            ->where('user_id', $user->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->orderBy('version')
            ->get()
            ->groupBy('assignment_id');

        return $assignments->mapWithKeys(function (Assignment $a) use ($submissionsByAssignment) {
            $latest = $submissionsByAssignment->get($a->id, new Collection)->sortByDesc('version')->first();

            return [$a->id => $this->statusForAssignment($latest)];
        })->filter();
    }

    private function statusForAssignment(?Submission $latest): ?array
    {
        if ($latest === null) {
            return null;
        }

        return match ($latest->status) {
            SubmissionStatus::Submitted => ['label' => 'Awaiting grading', 'tone' => 'gold'],
            SubmissionStatus::ReturnedForResubmission => ['label' => 'Needs revision', 'tone' => 'crimson'],
            // Graded — the checkmark already covers it.
            SubmissionStatus::Graded => null,
        };
    }

    /**
     * Mark a lesson complete for a student (idempotent), then recalculate the course
     * percentage and, at 100%, flip the enrollment to Completed.
     *
     * @return array{
     *     progress: LessonProgress,
     *     enrollment: ?Enrollment,
     *     percent: int,
     *     newly_completed: bool,
     *     module_completed: bool,
     *     course_completed: bool,
     *     next: ?Lesson,
     * }
     */
    public function markComplete(User $user, Lesson $lesson): array
    {
        $course = $this->courseFor($lesson);

        $progress = $this->progressRow($user, $lesson);
        $wasComplete = $progress->isComplete();

        if (! $wasComplete) {
            $progress->forceFill([
                'status' => LessonProgressStatus::Completed,
                'completed_at' => now(),
            ])->save();
        }

        $snapshot = $this->snapshot($user, $course);
        $enrollment = $this->recalculate($user, $course, $snapshot);

        return [
            'progress' => $progress,
            'enrollment' => $enrollment,
            'percent' => $snapshot->percent(),
            'newly_completed' => ! $wasComplete,
            'module_completed' => ! $wasComplete && $lesson->module instanceof Module
                && $snapshot->isModuleComplete($lesson->module),
            'course_completed' => ! $wasComplete && $snapshot->isCourseComplete(),
            'next' => $snapshot->next($lesson),
        ];
    }

    /**
     * Un-mark a completed lesson (back to in_progress) and recalculate — which can
     * drop a Completed enrollment back to Active.
     *
     * @return array{progress: LessonProgress, enrollment: ?Enrollment, percent: int}
     */
    public function markIncomplete(User $user, Lesson $lesson): array
    {
        $course = $this->courseFor($lesson);

        $progress = $this->progressRow($user, $lesson);
        $progress->forceFill([
            'status' => LessonProgressStatus::InProgress,
            'completed_at' => null,
        ])->save();

        $snapshot = $this->snapshot($user, $course);
        $enrollment = $this->recalculate($user, $course, $snapshot);

        return [
            'progress' => $progress,
            'enrollment' => $enrollment,
            'percent' => $snapshot->percent(),
        ];
    }

    /**
     * Persist a lightweight engagement ping: the last video position (for resume) and
     * cumulative seconds spent. Never downgrades a completed lesson.
     */
    public function recordPosition(User $user, Lesson $lesson, ?int $positionSeconds = null, ?int $secondsSpent = null): LessonProgress
    {
        $progress = $this->progressRow($user, $lesson);

        if (! $progress->isComplete() && $progress->status === LessonProgressStatus::NotStarted) {
            $progress->status = LessonProgressStatus::InProgress;
        }

        if ($positionSeconds !== null) {
            $progress->last_position_seconds = max(0, $positionSeconds);
        }

        if ($secondsSpent !== null) {
            // Monotonic: a stale or re-sent ping can't shrink recorded time.
            $progress->seconds_spent = max((int) $progress->seconds_spent, $secondsSpent);
        }

        $progress->save();

        return $progress;
    }

    /**
     * Recalculate and cache the course percentage on the student's enrollment, and
     * keep its Completed status in sync with 100%. Returns the enrollment, or null
     * when the student has no learning-bearing enrollment (e.g. staff previewing).
     */
    public function recalculate(User $user, Course $course, ?CourseProgress $snapshot = null): ?Enrollment
    {
        $enrollment = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        if (! $enrollment || ! $enrollment->grantsLearningAccess()) {
            return null;
        }

        $snapshot ??= $this->snapshot($user, $course);

        $percent = $snapshot->percent();
        $complete = $snapshot->isCourseComplete();

        $attributes = ['progress_percent' => $percent];
        $justCompleted = $complete && $enrollment->status !== EnrollmentStatus::Completed;

        if ($justCompleted) {
            $attributes['status'] = EnrollmentStatus::Completed;
            $attributes['completed_at'] = now();
        } elseif (! $complete && $enrollment->status === EnrollmentStatus::Completed) {
            // A lesson was un-marked — the course is no longer finished.
            $attributes['status'] = EnrollmentStatus::Active;
            $attributes['completed_at'] = null;
        }

        $enrollment->forceFill($attributes)->save();

        // Fired exactly on this not-Completed → Completed transition — the shared
        // pipeline Section 6.5's grade snapshot (and Section 7's certificate) hook.
        if ($justCompleted) {
            CourseCompleted::dispatch($user, $course, $enrollment);
        }

        return $enrollment;
    }

    /**
     * Fetch-or-build the single (user, lesson) progress row. firstOrNew (not create)
     * so reads don't write; callers persist.
     */
    private function progressRow(User $user, Lesson $lesson): LessonProgress
    {
        return LessonProgress::firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    /**
     * The flat, ordered lesson sequence for a course (module position, then lesson
     * position). Uses loaded relations when present; otherwise one ordered query.
     *
     * @return EloquentCollection<int, Lesson>
     */
    public function sequence(Course $course): EloquentCollection
    {
        if ($course->relationLoaded('modules')) {
            $lessons = $course->modules
                ->sortBy('position')
                ->flatMap(fn (Module $module) => ($module->relationLoaded('lessons')
                    ? $module->lessons
                    : $module->lessons()->get())->sortBy('position')->values())
                ->values();

            return new EloquentCollection($lessons->all());
        }

        return Lesson::query()
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->where('modules.course_id', $course->id)
            ->orderBy('modules.position')
            ->orderBy('lessons.position')
            ->orderBy('lessons.id')
            ->select('lessons.*')
            ->get();
    }

    /**
     * Resolve a lesson's course through its module without an extra query when the
     * relations are already loaded.
     */
    private function courseFor(Lesson $lesson): Course
    {
        $lesson->loadMissing('module.course');

        return $lesson->module->course;
    }
}
