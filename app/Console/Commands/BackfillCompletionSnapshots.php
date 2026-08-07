<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Services\Courses\LearningService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Stamps a completion snapshot onto enrollments that finished before Section 16 existed.
 *
 * Explicitly best-effort: what those students were ACTUALLY measured against was never
 * recorded and cannot be recovered. This freezes them against the curriculum as it stands
 * today, which stops any further drift but does not undo drift that already happened.
 * Snapshots written this way are flagged `backfilled` so the distinction stays visible.
 *
 * Idempotent — an enrollment that already has a snapshot is skipped, and the model's
 * write-once guard would refuse it anyway.
 */
class BackfillCompletionSnapshots extends Command
{
    protected $signature = 'courses:backfill-completion-snapshots
                            {--dry-run : Report what would be stamped without writing}';

    protected $description = 'Freeze the graded curriculum for course completions that predate completion snapshots';

    public function handle(LearningService $learning): int
    {
        $pending = Enrollment::query()
            ->where('status', EnrollmentStatus::Completed->value)
            ->whereNull('completion_snapshot')
            ->with('course')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Every completed enrollment already carries a snapshot. Nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->warn($pending->count().' completed enrolment(s) predate completion snapshots.');
        $this->line('These are frozen against the curriculum as it stands TODAY — best-effort,');
        $this->line('because what they were originally measured against was never recorded.');
        $this->newLine();

        // One snapshot per course, not per enrolment: every student completing the same
        // course is being frozen against the same live curriculum here.
        $byCourse = $pending->groupBy('course_id');
        $stamped = 0;

        foreach ($byCourse as $enrollments) {
            $course = $enrollments->first()->course;

            if ($course === null) {
                continue;
            }

            $snapshot = $learning->completionSnapshot($course);
            $payload = [...$snapshot->toArray(), 'backfilled' => true];

            $this->line(sprintf(
                '  %-46s %d student(s), %d lessons, %d graded items',
                Str::limit($course->title, 44),
                $enrollments->count(),
                \count($snapshot->lessonIds),
                \count($snapshot->gradedAssessmentIds) + \count($snapshot->gradedAssignmentIds),
            ));

            if ($dryRun) {
                continue;
            }

            foreach ($enrollments as $enrollment) {
                $enrollment->forceFill([
                    'completion_snapshot' => $payload,
                    'completion_snapshot_at' => now(),
                ])->save();

                $stamped++;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info('Dry run — nothing written. Re-run without --dry-run to stamp them.');

            return self::SUCCESS;
        }

        $this->info("Stamped {$stamped} enrolment(s). Their grades no longer move when the course does.");

        return self::SUCCESS;
    }
}
