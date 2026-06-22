<?php

namespace App\Services\Courses;

use App\Enums\AssessmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
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

        return new CourseProgress($course, $sequence, $progress, $required->count(), $requiredComplete);
    }

    /**
     * The unified learning outline (lessons + published assessments interleaved by
     * placement) with per-item completion and sequential lock state — what the player
     * sidebar renders and the gate consults.
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

        // Build the flat ordered rows first, then a single pass computes the lock frontier.
        $rows = new Collection;

        $pushAssessment = function (Assessment $a) use ($rows, $passedIds) {
            $rows->push([
                'kind' => 'assessment',
                'model' => $a,
                'completed' => $passedIds->contains($a->id),
                'required' => (bool) $a->is_required,
                'module_id' => $a->module_id,
                'placement' => $a->placement->value,
            ]);
        };

        foreach ($course->modules as $module) {
            $byPlacement = $assessments->where('module_id', $module->id);
            $byPlacement->where('placement', 'pre_module')->sortBy('position')->each($pushAssessment);

            foreach ($module->lessons as $lesson) {
                $rows->push([
                    'kind' => 'lesson',
                    'model' => $lesson,
                    'completed' => $snapshot->isComplete($lesson),
                    'required' => true,
                    'module_id' => $module->id,
                    'placement' => null,
                ]);
            }

            $byPlacement->where('placement', 'post_module')->sortBy('position')->each($pushAssessment);
        }

        $assessments->whereNull('module_id')->sortBy('position')->each($pushAssessment);

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

        if ($complete && $enrollment->status !== EnrollmentStatus::Completed) {
            $attributes['status'] = EnrollmentStatus::Completed;
            $attributes['completed_at'] = now();
        } elseif (! $complete && $enrollment->status === EnrollmentStatus::Completed) {
            // A lesson was un-marked — the course is no longer finished.
            $attributes['status'] = EnrollmentStatus::Active;
            $attributes['completed_at'] = null;
        }

        $enrollment->forceFill($attributes)->save();

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
