<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Notifications\PendingEnrollmentDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Batches "new pending enrollment" into one notification per course every 15
 * minutes, instead of one per request — pending_digested_at is the idempotency
 * flag, set the moment a request has been folded into a digest.
 */
class SendPendingEnrollmentDigest extends Command
{
    protected $signature = 'notifications:pending-enrollment-digest';

    protected $description = 'Notify instructors of pending enrolment requests that arrived since the last digest';

    public function handle(): int
    {
        $courses = 0;

        Enrollment::query()
            ->where('status', EnrollmentStatus::Pending->value)
            ->whereNull('pending_digested_at')
            ->with('course.instructors')
            ->get()
            ->groupBy('course_id')
            ->each(function ($enrollments) use (&$courses) {
                $course = $enrollments->first()->course;

                Notification::send($course->instructors, new PendingEnrollmentDigestNotification($course, $enrollments->count()));

                Enrollment::whereIn('id', $enrollments->pluck('id'))->update(['pending_digested_at' => now()]);

                $courses++;
            });

        $this->info("Digested pending enrolments for {$courses} course(s).");

        return self::SUCCESS;
    }
}
