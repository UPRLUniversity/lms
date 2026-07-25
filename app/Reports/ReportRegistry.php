<?php

namespace App\Reports;

use App\Reports\Contracts\Report;
use Illuminate\Support\Collection;

/**
 * The four report-centre reports, resolved by their url key. One place to register a
 * report so the controller, exports and queued job all agree on what exists.
 */
class ReportRegistry
{
    /** @var array<int, class-string<Report>> */
    private const REPORTS = [
        LearnerReport::class,
        InstructorReport::class,
        ComplianceReport::class,
        CertificationReport::class,
    ];

    /**
     * @return Collection<int, Report>
     */
    public function all(): Collection
    {
        return collect(self::REPORTS)->map(fn (string $class) => app($class));
    }

    /**
     * Resolve a report by key, or null when the key is unknown.
     */
    public function find(string $key): ?Report
    {
        $report = $this->all()->first(fn (Report $r) => $r->key() === $key);

        return $report;
    }

    /**
     * Resolve a report by key or 404.
     */
    public function findOrFail(string $key): Report
    {
        return $this->find($key) ?? abort(404);
    }
}
