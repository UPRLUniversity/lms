<?php

namespace Database\Seeders;

use App\Enums\ProgressionMode;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Courses\LearningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * A clickable progress demo on top of the enrolment spread: the showcase student
 * (student1) lands mid-course on PRL101, two students have finished it (100% +
 * completion date), and PRL220 is switched to sequential progression with one lesson
 * done — so locked lessons (and the server-side block) are demonstrable. Idempotent.
 */
class ProgressSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::role(Role::Student->value)->where('is_active', true)->orderBy('id')->get()->values();
        if ($students->count() < 14) {
            return;
        }

        $learning = app(LearningService::class);
        $s = fn (int $i) => $students[$i % $students->count()];

        // PRL101 — the showcase open course. Build a realistic spread of progress.
        if ($prl101 = $this->course('PRL101')) {
            $lessons = $learning->sequence($prl101);

            // Showcase student (student1): first module done → resumes mid-course.
            $this->completeUpTo($learning, $s(0), $lessons, 3);

            // A second learner a little further along.
            $this->completeUpTo($learning, $s(1), $lessons, 5);

            // The two seeded "Completed" enrolments actually finish every lesson, so
            // their 100% / completion date are real.
            $this->completeUpTo($learning, $s(12), $lessons, $lessons->count());
            $this->completeUpTo($learning, $s(13), $lessons, $lessons->count());
        }

        // PRL220 — demonstrate sequential progression + locking.
        if ($prl220 = $this->course('PRL220')) {
            $prl220->update(['progression_mode' => ProgressionMode::Sequential->value]);

            $lessons = $learning->sequence($prl220);
            // student1 completes only the first lesson → lesson 2 unlocks, the rest lock.
            $this->completeUpTo($learning, $s(0), $lessons, 1);
        }
    }

    /**
     * Record some watch time then mark the first $count lessons complete for a student
     * — going through the service so the enrollment percentage/status stay truthful.
     *
     * @param  Collection<int, Lesson>  $lessons
     */
    private function completeUpTo(LearningService $learning, User $student, $lessons, int $count): void
    {
        foreach ($lessons->take($count) as $lesson) {
            /** @var Lesson $lesson */
            $seconds = max(60, (int) $lesson->duration_minutes * 60);
            $learning->recordPosition($student, $lesson, $seconds, $seconds);
            $learning->markComplete($student, $lesson);
        }
    }

    private function course(string $code): ?Course
    {
        return Course::where('code', $code)->with('modules.lessons')->first();
    }
}
