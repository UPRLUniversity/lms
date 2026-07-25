<?php

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Models\GeneratedReport;
use App\Reports\ReportRegistry;
use App\Services\Reporting\ReportExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Builds a large report export off the request (Section 10): materialises the rows,
 * writes the file to the private disk and fires the "your report is ready" notification.
 * Queued on the database driver like all the app's background work, so a heavy export
 * never blocks the admin who asked for it.
 */
class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $generatedReportId) {}

    public function handle(ReportRegistry $registry, ReportExporter $exporter): void
    {
        $generated = GeneratedReport::find($this->generatedReportId);

        if ($generated === null || $generated->status === ReportStatus::Ready) {
            return;
        }

        $report = $registry->find($generated->report);

        if ($report === null) {
            $generated->update(['status' => ReportStatus::Failed]);

            return;
        }

        $exporter->fulfil($generated, $report);
    }

    public function failed(Throwable $e): void
    {
        GeneratedReport::whereKey($this->generatedReportId)->update(['status' => ReportStatus::Failed->value]);
    }
}
