<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Courses\LearningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gives the Section-10 dashboards & reports something to show on a fresh seed: recent
 * sign-in times (so "active users (30d)" is a real, non-zero figure) and a spread of
 * back-dated enrolments across the last twelve months (so the enrolment-trend chart and
 * "top courses" read like a lived-in platform). Everything here is real data the reports
 * then aggregate — never a hard-coded dashboard number. Idempotent: enrolments are only
 * added for (student, course) pairs that don't already exist, so it respects the unique
 * constraint and can re-run on migrate:fresh --seed.
 *
 * Two invariants this seeder must uphold, because earlier sections' demos depend on them
 * and it writes rows directly rather than through EnrollmentService:
 *
 *   1. CAPACITY. A seat-holding status (active/pending) is only added while the course
 *      still has a free seat, so a capped course can never end up over its limit. Getting
 *      this wrong doesn't just misprint the roster meter ("10 / 3") — it silently kills
 *      the Section-3 waitlist auto-promote demo, because a withdrawal frees nothing while
 *      the course is still over capacity. Completed enrolments release their seat, so they
 *      stay unrestricted and keep feeding the trend / top-courses / completion-rate stats.
 *   2. HONEST PROGRESS. A back-dated enrolment gets real `lesson_progress` rows matching
 *      its percentage, so the instructor's progress heat-strip, the course analytics and
 *      the learner's own player all agree with the cached `progress_percent`. Rows are
 *      bulk-inserted rather than driven through LearningService precisely because these
 *      are historical records: replaying them through the live pipeline would re-fire
 *      course completion, re-issue certificates and re-notify for events that "happened"
 *      months ago.
 */
class ReportingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->stampLogins();
        $this->backdateEnrolments();
    }

    /**
     * Realistic last-seen times: most of the roster active within the month, a tail
     * beyond it, and a couple never signed in.
     */
    private function stampLogins(): void
    {
        User::query()->orderBy('id')->get()->each(function (User $user, int $i): void {
            // ~70% within 30 days, ~20% within 31–90 days, ~10% never.
            $bucket = $i % 10;
            $user->forceFill([
                'last_login_at' => match (true) {
                    $bucket < 7 => now()->subDays(($i % 29) + 1),
                    $bucket < 9 => now()->subDays(31 + ($i % 55)),
                    default => null,
                },
            ])->save();
        });
    }

    /**
     * Spread extra enrolments across the last twelve months on published courses so the
     * trend line and top-courses list are populated. Most are completed (feeding the
     * completion-rate stat); a few stay active — but only where a seat is genuinely free.
     */
    private function backdateEnrolments(): void
    {
        $students = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::Student->value))
            ->get();

        $courses = Course::query()
            ->where('status', CourseStatus::Published->value)
            ->get();

        if ($students->isEmpty() || $courses->isEmpty()) {
            return;
        }

        // Seats already held per course (active + pending), counted once up front and
        // then tracked in memory as we add, so we never exceed a capped course.
        $seatsTaken = Enrollment::query()
            ->whereIn('status', EnrollmentStatus::seatHolders())
            ->selectRaw('course_id, count(*) as n')
            ->groupBy('course_id')
            ->pluck('n', 'course_id')
            ->all();

        $lessonsByCourse = $this->lessonsByCourse($courses);

        $created = 0;
        $skippedForCapacity = 0;
        $progressRows = [];

        // Walk months 1..11 ago; each month enrols a slice of students into a rotating
        // set of courses, skewing earlier months toward completion.
        for ($monthsAgo = 11; $monthsAgo >= 1; $monthsAgo--) {
            $when = now()->startOfMonth()->subMonths($monthsAgo)->addDays(3 + ($monthsAgo % 20));

            foreach ($courses as $ci => $course) {
                // 2–4 students per course per month, rotating through the roster.
                $take = 2 + (($monthsAgo + $ci) % 3);
                for ($k = 0; $k < $take; $k++) {
                    $student = $students[($monthsAgo * 3 + $ci * 2 + $k) % $students->count()];

                    if (Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->exists()) {
                        continue;
                    }

                    // Older cohorts are more likely to have finished.
                    $completed = ($monthsAgo >= 3) && (($k + $ci) % 3 !== 0);

                    // A seat-holding (active) row may only be added while a seat is free;
                    // a completed one frees its seat, so it is always safe.
                    if (! $completed && $this->isFull($course, $seatsTaken)) {
                        $skippedForCapacity++;

                        continue;
                    }

                    $percent = $completed ? 100 : (20 + (($k + $ci) * 13) % 70);
                    $finishedAt = $completed ? (clone $when)->addDays(14) : null;

                    Enrollment::create([
                        'user_id' => $student->id,
                        'course_id' => $course->id,
                        'status' => $completed ? EnrollmentStatus::Completed->value : EnrollmentStatus::Active->value,
                        'source' => EnrollmentSource::Self->value,
                        'enrolled_at' => $when,
                        'progress_percent' => $percent,
                        'completed_at' => $finishedAt,
                    ]);

                    if (! $completed) {
                        $seatsTaken[$course->id] = ($seatsTaken[$course->id] ?? 0) + 1;
                    }

                    // Back-date matching lesson progress so the percentage isn't a bare
                    // cached number with nothing behind it.
                    array_push($progressRows, ...$this->progressRowsFor(
                        $student,
                        $lessonsByCourse[$course->id] ?? collect(),
                        $percent,
                        $finishedAt ?? $when,
                    ));

                    $created++;
                }
            }
        }

        foreach (array_chunk($progressRows, 500) as $chunk) {
            DB::table('lesson_progress')->insertOrIgnore($chunk);
        }

        $this->command?->info(sprintf(
            'ReportingDemoSeeder: %d historical enrolments, %d lesson-progress rows, %d skipped to respect course capacity.',
            $created,
            count($progressRows),
            $skippedForCapacity,
        ));
    }

    /**
     * Whether a capped course has no free seat left, using the in-memory running count.
     *
     * @param  array<int, int>  $seatsTaken
     */
    private function isFull(Course $course, array $seatsTaken): bool
    {
        return $course->capacity !== null
            && ($seatsTaken[$course->id] ?? 0) >= (int) $course->capacity;
    }

    /**
     * Lessons per course, in the same curriculum order the player walks.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<int, Collection<int, Lesson>>
     */
    private function lessonsByCourse(Collection $courses): array
    {
        $learning = app(LearningService::class);

        return $courses
            ->mapWithKeys(fn (Course $course) => [$course->id => $learning->sequence($course)])
            ->all();
    }

    /**
     * The first N lessons marked complete, where N matches the enrolment's percentage —
     * so a 100% enrolment really has every lesson done and a 40% one really has ~40%.
     * Time spent is the lesson's own estimate, so "time spent" columns read sensibly too.
     *
     * @param  Collection<int, Lesson>  $lessons
     * @return array<int, array<string, mixed>>
     */
    private function progressRowsFor(User $student, Collection $lessons, int $percent, mixed $at): array
    {
        if ($lessons->isEmpty()) {
            return [];
        }

        $done = (int) floor($lessons->count() * $percent / 100);

        return $lessons->take($done)->map(function (Lesson $lesson) use ($student, $at): array {
            $seconds = max(60, (int) $lesson->duration_minutes * 60);

            return [
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
                'status' => LessonProgressStatus::Completed->value,
                'completed_at' => $at,
                'seconds_spent' => $seconds,
                'last_position_seconds' => $seconds,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        })->values()->all();
    }
}
