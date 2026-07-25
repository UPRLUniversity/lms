<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Gives the Section-10 dashboards & reports something to show on a fresh seed: recent
 * sign-in times (so "active users (30d)" is a real, non-zero figure) and a spread of
 * back-dated enrolments across the last twelve months (so the enrolment-trend chart and
 * "top courses" read like a lived-in platform). Everything here is real data the reports
 * then aggregate — never a hard-coded dashboard number. Idempotent: enrolments are only
 * added for (student, course) pairs that don't already exist, so it respects the unique
 * constraint and can re-run on migrate:fresh --seed.
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
     * completion-rate stat); a few stay active.
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

        $created = 0;
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

                    Enrollment::create([
                        'user_id' => $student->id,
                        'course_id' => $course->id,
                        'status' => $completed ? EnrollmentStatus::Completed->value : EnrollmentStatus::Active->value,
                        'source' => EnrollmentSource::Self->value,
                        'enrolled_at' => $when,
                        'progress_percent' => $completed ? 100 : (20 + (($k + $ci) * 13) % 70),
                        'completed_at' => $completed ? (clone $when)->addDays(14) : null,
                    ]);
                    $created++;
                }
            }
        }

        $this->command?->info("ReportingDemoSeeder: added {$created} historical enrolments.");
    }
}
