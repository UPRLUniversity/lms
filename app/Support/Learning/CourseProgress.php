<?php

namespace App\Support\Learning;

use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use Illuminate\Support\Collection;

/**
 * An immutable, query-free snapshot of one student's progress through one course:
 * the ordered lesson sequence, a per-lesson progress map, and every derived fact the
 * player and sidebar need (percent, locking, neighbours, resume target).
 *
 * Built once per request by LearningService::snapshot() from a single progress query
 * and the already-loaded curriculum, so the sidebar renders with no N+1.
 */
class CourseProgress
{
    /**
     * @param  Collection<int, Lesson>  $sequence  flat, ordered (module then lesson position)
     * @param  Collection<int, LessonProgress>  $progress  keyed by lesson_id
     * @param  int  $requiredAssessmentTotal  published, required assessments on the course
     * @param  int  $requiredAssessmentComplete  of those, the ones the student has passed
     */
    public function __construct(
        public readonly Course $course,
        public readonly Collection $sequence,
        public readonly Collection $progress,
        public readonly int $requiredAssessmentTotal = 0,
        public readonly int $requiredAssessmentComplete = 0,
    ) {}

    /**
     * Lesson-only counts — for the "X of Y lessons" labels the sidebar shows.
     */
    public function lessonTotal(): int
    {
        return $this->sequence->count();
    }

    public function lessonCompletedCount(): int
    {
        return $this->sequence
            ->filter(fn (Lesson $l) => $this->isComplete($l))
            ->count();
    }

    /**
     * Total trackable items toward course completion: every lesson plus every required
     * assessment. With no assessments this equals the lesson count, so the lesson-only
     * player (Section 4) is unaffected.
     */
    public function total(): int
    {
        return $this->lessonTotal() + $this->requiredAssessmentTotal;
    }

    public function completedCount(): int
    {
        return $this->lessonCompletedCount() + $this->requiredAssessmentComplete;
    }

    /**
     * Whole-course completion percentage (0–100), floored so it only reads 100 when every
     * lesson AND every required assessment is genuinely done.
     */
    public function percent(): int
    {
        $total = $this->total();

        return $total === 0 ? 0 : (int) floor($this->completedCount() / $total * 100);
    }

    public function isCourseComplete(): bool
    {
        return $this->total() > 0 && $this->completedCount() === $this->total();
    }

    public function totalSecondsSpent(): int
    {
        return (int) $this->progress->sum('seconds_spent');
    }

    public function stateFor(Lesson $lesson): LessonProgressStatus
    {
        return $this->progress->get($lesson->id)?->status ?? LessonProgressStatus::NotStarted;
    }

    public function isComplete(Lesson $lesson): bool
    {
        return $this->stateFor($lesson) === LessonProgressStatus::Completed;
    }

    public function lastPosition(Lesson $lesson): int
    {
        return (int) ($this->progress->get($lesson->id)?->last_position_seconds ?? 0);
    }

    /**
     * The index (0-based) of the first lesson that isn't yet completed — the furthest
     * point a sequential learner has unlocked. Null when every lesson is complete.
     */
    public function firstIncompleteIndex(): ?int
    {
        foreach ($this->sequence as $i => $lesson) {
            if (! $this->isComplete($lesson)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * The lesson a "Continue learning" link should resume to: the first incomplete
     * lesson, or — when all are done — the very first lesson (revisiting a finished
     * course). Null only when the course has no lessons at all.
     */
    public function resumeLesson(): ?Lesson
    {
        $index = $this->firstIncompleteIndex();

        if ($index !== null) {
            return $this->sequence->get($index);
        }

        return $this->sequence->first();
    }

    /**
     * In sequential mode, a lesson is locked when it sits beyond the first lesson the
     * learner hasn't completed. Free courses never lock anything.
     */
    public function isLocked(Lesson $lesson): bool
    {
        if (! $this->course->isSequential()) {
            return false;
        }

        $firstIncomplete = $this->firstIncompleteIndex();

        // Everything completed → nothing is locked.
        if ($firstIncomplete === null) {
            return false;
        }

        return $this->indexOf($lesson) > $firstIncomplete;
    }

    public function previous(Lesson $lesson): ?Lesson
    {
        $index = $this->indexOf($lesson);

        return $index > 0 ? $this->sequence->get($index - 1) : null;
    }

    public function next(Lesson $lesson): ?Lesson
    {
        $index = $this->indexOf($lesson);

        return $index === null ? null : $this->sequence->get($index + 1);
    }

    /**
     * Whether all lessons in $module are completed (a module "tick").
     */
    public function isModuleComplete(Module $module): bool
    {
        $lessons = $this->sequence->where('module_id', $module->id);

        return $lessons->isNotEmpty() && $lessons->every(fn (Lesson $l) => $this->isComplete($l));
    }

    /**
     * Position of a lesson within the flat sequence, by id (handles detached
     * instances), or null when it isn't part of this course.
     */
    public function indexOf(Lesson $lesson): ?int
    {
        $index = $this->sequence->search(fn (Lesson $l) => $l->id === $lesson->id);

        return $index === false ? null : $index;
    }
}
