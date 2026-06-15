<?php

namespace Database\Seeders;

use App\Enums\EnrollmentMode;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A clickable enrolment demo across every mode and status: an open course with a
 * healthy cohort, an approval course with a pending queue, a capacity-3 course that's
 * full with a waitlist (so a single withdrawal visibly auto-promotes), and an
 * invite-only course filled by staff. Idempotent — keyed on (user, course).
 */
class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $students = User::role(Role::Student->value)->where('is_active', true)->orderBy('id')->get()->values();
        $admin = User::role(Role::Admin->value)->first();

        if ($students->count() < 12 || ! $admin) {
            return;
        }

        // 1) Configure the demo courses' enrolment settings.
        $config = [
            'PRL101' => [EnrollmentMode::Open, null],
            'LDS201' => [EnrollmentMode::Approval, null],
            'PRL305' => [EnrollmentMode::Open, 3],
            'JRN210' => [EnrollmentMode::InviteOnly, null],
            'PRL220' => [EnrollmentMode::Open, 30],
            'LDS110' => [EnrollmentMode::Approval, 5],
        ];

        $courses = Course::whereIn('code', array_keys($config))->get()->keyBy('code');

        foreach ($config as $code => [$mode, $capacity]) {
            $courses[$code]?->update([
                'enrollment_mode' => $mode->value,
                'capacity' => $capacity,
            ]);
        }

        $s = fn (int $i) => $students[$i % $students->count()];

        // 2) PRL101 — open, uncapped: a healthy active cohort + a couple of completions.
        if ($c = $courses['PRL101'] ?? null) {
            foreach (range(0, 11) as $i) {
                $this->enrol($c, $s($i), EnrollmentStatus::Active, EnrollmentSource::Self, now()->subDays(30 - $i));
            }
            $this->enrol($c, $s(12), EnrollmentStatus::Completed, EnrollmentSource::Self, now()->subDays(40));
            $this->enrol($c, $s(13), EnrollmentStatus::Completed, EnrollmentSource::Self, now()->subDays(42));
        }

        // 3) LDS201 — approval: a pending queue plus already-approved students.
        if ($c = $courses['LDS201'] ?? null) {
            foreach (range(0, 2) as $i) {
                $this->enrol($c, $s($i), EnrollmentStatus::Pending, EnrollmentSource::Self, now()->subDays(3 - $i));
            }
            foreach (range(3, 7) as $i) {
                $this->enrol($c, $s($i), EnrollmentStatus::Active, EnrollmentSource::Self, now()->subDays(20 - $i), $admin);
            }
        }

        // 4) PRL305 — capacity 3, FULL with a waitlist. Withdraw one active student to
        //    watch the first waitlisted (position #1) auto-promote.
        if ($c = $courses['PRL305'] ?? null) {
            foreach (range(0, 2) as $i) {
                $this->enrol($c, $s($i), EnrollmentStatus::Active, EnrollmentSource::Self, now()->subDays(15 - $i));
            }
            foreach (range(3, 5) as $i) {
                // Increasing enrolled_at → FIFO waitlist positions 1, 2, 3.
                $this->enrol($c, $s($i), EnrollmentStatus::Waitlisted, EnrollmentSource::Self, now()->subHours(10 - $i));
            }
        }

        // 5) JRN210 — invite-only: filled by staff (source admin). Includes the demo
        //    student (index 0) so the showcase account demonstrates the invite path.
        if ($c = $courses['JRN210'] ?? null) {
            foreach ([0, 6, 7, 8, 9, 10] as $i) {
                $this->enrol($c, $s($i), EnrollmentStatus::Active, EnrollmentSource::Admin, now()->subDays(12), $admin);
            }
        }

        // 6) PRL220 — open with history: active, a withdrawal and a bulk import.
        if ($c = $courses['PRL220'] ?? null) {
            $this->enrol($c, $s(0), EnrollmentStatus::Active, EnrollmentSource::Self, now()->subDays(9));
            $this->enrol($c, $s(1), EnrollmentStatus::Withdrawn, EnrollmentSource::Self, now()->subDays(8));
            $this->enrol($c, $s(2), EnrollmentStatus::Active, EnrollmentSource::Bulk, now()->subDays(7), $admin);
            $this->enrol($c, $s(3), EnrollmentStatus::Active, EnrollmentSource::Bulk, now()->subDays(7), $admin);
        }

        // 7) LDS110 — approval, capacity 5: full of active, with a pending and a reject.
        if ($c = $courses['LDS110'] ?? null) {
            foreach (range(0, 4) as $i) {
                $this->enrol($c, $s($i), EnrollmentStatus::Active, EnrollmentSource::Self, now()->subDays(25 - $i), $admin);
            }
            $this->enrol($c, $s(5), EnrollmentStatus::Pending, EnrollmentSource::Self, now()->subDay());
            $this->enrol($c, $s(6), EnrollmentStatus::Rejected, EnrollmentSource::Self, now()->subDays(2), $admin);
        }
    }

    private function enrol(Course $course, User $student, EnrollmentStatus $status, EnrollmentSource $source, \DateTimeInterface $at, ?User $approver = null): void
    {
        Enrollment::updateOrCreate(
            ['user_id' => $student->id, 'course_id' => $course->id],
            [
                'status' => $status->value,
                'source' => $source->value,
                'enrolled_at' => $at,
                'approved_by' => $approver?->id,
            ],
        );
    }
}
