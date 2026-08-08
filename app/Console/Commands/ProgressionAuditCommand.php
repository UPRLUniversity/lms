<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentStatus;
use App\Enums\ProgressionRule;
use App\Models\Enrollment;
use App\Models\Programme;
use App\Models\User;
use App\Services\Courses\ProgressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Who would this rule have blocked?
 *
 * Switching a programme to `sequential` never revokes access anybody already has — the
 * gate applies at enrolment time only. But "nothing breaks" is not the same as "nothing
 * changes", and an administrator deserves to see the blast radius BEFORE they flip the
 * switch rather than after a student writes in.
 *
 * Reports live enrolments the rule would refuse today. Changes nothing, ever.
 */
class ProgressionAuditCommand extends Command
{
    protected $signature = 'progression:audit
                            {programme? : Programme code or slug. Omit to audit every programme}
                            {--all : Include programmes still set to `open`, to preview what switching one on would do}';

    protected $description = 'List students enrolled in courses the progression rule would now block';

    public function handle(): int
    {
        $programmes = $this->programmes();

        if ($programmes->isEmpty()) {
            // Two different empties. Naming a programme that does not exist is a mistake
            // worth a non-zero exit; "nothing is sequential yet" is the ordinary state of
            // a system where nobody has switched anything on, and failing on it would
            // make the command useless in exactly the situation it is built for.
            if ($this->argument('programme') !== null) {
                $this->components->error("No programme matches “{$this->argument('programme')}”.");

                return self::FAILURE;
            }

            $this->components->info('No programme is set to sequential progression. Pass a code (e.g. CPR) or --all to preview what switching one on would do.');

            return self::SUCCESS;
        }

        $grandTotal = 0;

        foreach ($programmes as $programme) {
            $grandTotal += $this->auditProgramme($programme);
        }

        $this->newLine();

        if ($grandTotal === 0) {
            $this->components->info('No live enrolment would be blocked by the rules as configured.');
        } else {
            $this->components->warn("{$grandTotal} live ".str('enrolment')->plural($grandTotal).' would be refused if made today. None has been changed.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Programme>
     */
    private function programmes(): Collection
    {
        $argument = $this->argument('programme');

        $query = Programme::query()->with('parts.courses')->ordered();

        if ($argument !== null) {
            return $query->where('code', str($argument)->upper()->value())
                ->orWhere('slug', $argument)
                ->get();
        }

        // Without --all only the programmes actually enforcing a rule are audited; the
        // rest would report a hypothetical nobody asked for.
        return $query
            ->when(! $this->option('all'), fn ($q) => $q->where('progression_rule', ProgressionRule::Sequential->value))
            ->get();
    }

    private function auditProgramme(Programme $programme): int
    {
        $rule = $programme->progression_rule;

        $this->newLine();
        $this->components->twoColumnDetail(
            "<options=bold>{$programme->code}</> {$programme->name}",
            $rule === ProgressionRule::Sequential ? '<fg=yellow>sequential</>' : '<fg=gray>open (hypothetical)</>',
        );

        $courseIds = $programme->parts->flatMap(fn ($part) => $part->courses->pluck('id'))->unique();

        if ($courseIds->isEmpty()) {
            $this->components->twoColumnDetail('  no courses placed', '');

            return 0;
        }

        // Live enrolments only. A withdrawn or rejected student is not "in" anything, and
        // reporting them would inflate the number the admin is trying to judge.
        $enrollments = Enrollment::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Waitlisted->value,
                EnrollmentStatus::Completed->value,
            ])
            ->with(['user', 'course'])
            ->get();

        $rows = [];

        // Grouped by student so each one's passed-course set is resolved once, however
        // many of this programme's courses they are enrolled in.
        foreach ($enrollments->groupBy('user_id') as $forStudent) {
            $student = $forStudent->first()->user;

            if (! $student instanceof User) {
                continue;
            }

            $courses = $forStudent->map(fn (Enrollment $e) => $e->course)->filter()->unique('id')->values();

            // A fresh service per student: the memo is per-instance and per-user, and this
            // command may walk thousands of students in one run.
            //
            // assumeSequential makes an `open` programme answer the hypothetical the admin
            // is actually asking — "what would happen if I switched this on?" — which is
            // only useful BEFORE the switch.
            $verdicts = app(ProgressionService::class)->verdictsFor($student, $courses, $programme);

            foreach ($forStudent as $enrollment) {
                $verdict = $verdicts->get($enrollment->course_id);

                if (! $verdict?->isBlocked()) {
                    continue;
                }

                $rows[] = [
                    $student->name,
                    $enrollment->course->code,
                    $enrollment->status->value,
                    $verdict->blockingPart?->name ?? '—',
                    $enrollment->overrodePrerequisites() ? 'yes' : '',
                ];
            }
        }

        if ($rows === []) {
            $this->components->twoColumnDetail('  <fg=green>nothing would be blocked</>', (string) $enrollments->count().' live enrolments checked');

            return 0;
        }

        $this->table(['Student', 'Course', 'Status', 'Blocked by', 'Override'], $rows);

        return count($rows);
    }
}
