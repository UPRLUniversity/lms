<?php

namespace Tests\Unit;

use App\Enums\LessonProgressStatus;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Support\Learning\CourseProgress;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Pure-logic tests for the progress snapshot: percent, locking, neighbours and the
 * resume target. Built from in-memory models so there's no database dependency.
 */
class CourseProgressTest extends TestCase
{
    /**
     * Build a snapshot from a free/sequential flag, N lessons, and a set of completed
     * lesson indexes — without touching the DB.
     */
    private function snapshot(bool $sequential, int $lessonCount, array $completedIndexes): CourseProgress
    {
        $course = new Course(['progression_mode' => $sequential ? 'sequential' : 'free']);

        $module = new Module;
        $module->id = 1;

        $lessons = collect(range(1, $lessonCount))->map(function (int $i) use ($module) {
            $lesson = new Lesson(['title' => "Lesson {$i}"]);
            $lesson->id = $i;
            $lesson->module_id = $module->id;
            $lesson->setRelation('module', $module);

            return $lesson;
        });

        $progress = collect($completedIndexes)->mapWithKeys(function (int $index) {
            $row = new LessonProgress([
                'status' => LessonProgressStatus::Completed->value,
            ]);
            $row->lesson_id = $index;

            return [$index => $row];
        });

        return new CourseProgress($course, new Collection($lessons->all()), $progress);
    }

    public function test_percent_is_floored_completed_over_total(): void
    {
        $this->assertSame(0, $this->snapshot(false, 4, [])->percent());
        $this->assertSame(25, $this->snapshot(false, 4, [1])->percent());
        // 1 of 3 floors to 33, never rounding up to a misleading number.
        $this->assertSame(33, $this->snapshot(false, 3, [1])->percent());
        $this->assertSame(100, $this->snapshot(false, 4, [1, 2, 3, 4])->percent());
    }

    public function test_course_complete_only_when_every_lesson_done(): void
    {
        $this->assertFalse($this->snapshot(false, 3, [1, 2])->isCourseComplete());
        $this->assertTrue($this->snapshot(false, 3, [1, 2, 3])->isCourseComplete());
    }

    public function test_free_course_never_locks(): void
    {
        $snapshot = $this->snapshot(false, 4, []);

        foreach ($snapshot->sequence as $lesson) {
            $this->assertFalse($snapshot->isLocked($lesson));
        }
    }

    public function test_sequential_locks_everything_beyond_first_incomplete(): void
    {
        // Nothing done → only the first lesson is open.
        $snapshot = $this->snapshot(true, 4, []);
        $this->assertFalse($snapshot->isLocked($snapshot->sequence[0]));
        $this->assertTrue($snapshot->isLocked($snapshot->sequence[1]));
        $this->assertTrue($snapshot->isLocked($snapshot->sequence[3]));

        // First two done → third is the new frontier (open), fourth still locked.
        $snapshot = $this->snapshot(true, 4, [1, 2]);
        $this->assertFalse($snapshot->isLocked($snapshot->sequence[2]));
        $this->assertTrue($snapshot->isLocked($snapshot->sequence[3]));

        // All done → nothing locked.
        $snapshot = $this->snapshot(true, 4, [1, 2, 3, 4]);
        $this->assertFalse($snapshot->isLocked($snapshot->sequence[3]));
    }

    public function test_resume_targets_first_incomplete_then_falls_back_to_start(): void
    {
        $this->assertSame(1, $this->snapshot(false, 3, [])->resumeLesson()->id);
        $this->assertSame(3, $this->snapshot(false, 3, [1, 2])->resumeLesson()->id);
        // All complete → revisit from the very first lesson.
        $this->assertSame(1, $this->snapshot(false, 3, [1, 2, 3])->resumeLesson()->id);
    }

    public function test_neighbours_walk_the_flat_sequence(): void
    {
        $snapshot = $this->snapshot(false, 3, []);

        $this->assertNull($snapshot->previous($snapshot->sequence[0]));
        $this->assertSame(2, $snapshot->next($snapshot->sequence[0])->id);
        $this->assertSame(1, $snapshot->previous($snapshot->sequence[1])->id);
        $this->assertNull($snapshot->next($snapshot->sequence[2]));
    }
}
