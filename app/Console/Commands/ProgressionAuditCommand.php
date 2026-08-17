<?php

namespace App\Console\Commands;

use App\Enums\ProgressionRule;
use App\Models\Programme;
use App\Services\Courses\ProgressionAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Who would this rule have blocked?
 *
 * The estate-wide form of the impact panel the programme form shows inline: same numbers,
 * same service, but able to sweep every programme at once, which is the thing a terminal is
 * better at than a form. An administrator never has to run this — the screen where they
 * make the decision already answers the question for the programme in front of them.
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

        $impact = app(ProgressionAuditService::class)->forProgramme($programme);

        if ($impact->isClear()) {
            $this->components->twoColumnDetail(
                '  <fg=green>nothing would be blocked</>',
                $impact->checked.' live enrolments checked',
            );

            return 0;
        }

        $this->table(
            ['Student', 'Course', 'Status', 'Blocked by', 'Override'],
            $impact->rows->map(fn (array $row) => [
                $row['student'],
                $row['course'],
                $row['status'],
                $row['blockedBy'] ?? '—',
                $row['override'] ? 'yes' : '',
            ])->all(),
        );

        return $impact->blockedCount();
    }
}
