<?php

namespace App\Console\Commands;

use App\Enums\AssignmentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Assignment;
use App\Notifications\AssignmentDueSoonNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reminds enrolled students of a published assignment due within 48 hours, once
 * per (assignment, student) — assignment_due_reminders is the idempotency flag, so
 * running this hourly (to catch the 48h window precisely) never double-reminds.
 * A student who has already submitted is skipped; a late submission after the
 * reminder still works normally (allow_late is untouched by this command).
 */
class SendAssignmentDueSoonReminders extends Command
{
    protected $signature = 'notifications:due-soon';

    protected $description = 'Remind enrolled students about assignments due within 48 hours';

    public function handle(): int
    {
        $windowEnd = now()->addHours(48);
        $sent = 0;

        Assignment::query()
            ->where('status', AssignmentStatus::Published->value)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), $windowEnd])
            ->with('course')
            ->each(function (Assignment $assignment) use (&$sent) {
                $alreadyReminded = DB::table('assignment_due_reminders')
                    ->where('assignment_id', $assignment->id)
                    ->pluck('user_id')
                    ->all();

                $submitted = $assignment->submissions()->pluck('user_id')->unique()->all();

                $students = $assignment->course->enrollments()
                    ->where('status', EnrollmentStatus::Active->value)
                    ->whereNotIn('user_id', array_merge($alreadyReminded, $submitted))
                    ->with('user')
                    ->get()
                    ->pluck('user')
                    ->filter();

                foreach ($students as $student) {
                    $student->notify(new AssignmentDueSoonNotification($assignment));

                    DB::table('assignment_due_reminders')->insert([
                        'assignment_id' => $assignment->id,
                        'user_id' => $student->id,
                        'sent_at' => now(),
                    ]);

                    $sent++;
                }
            });

        $this->info("Sent {$sent} due-soon reminder(s).");

        return self::SUCCESS;
    }
}
