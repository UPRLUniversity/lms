<?php

namespace App\Services\Reporting;

use App\Enums\ReportStatus;
use App\Exports\ReportExport;
use App\Models\GeneratedReport;
use App\Notifications\ReportReadyNotification;
use App\Reports\Contracts\Report;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a report + filters + format into a file — the single path shared by the inline
 * download (small reports) and the queued job (large ones), so a CSV, XLSX or PDF is
 * produced identically whichever route it takes. Formats: xlsx + csv (maatwebsite/excel)
 * and pdf (one branded dompdf layout via ReportPdf).
 */
class ReportExporter
{
    /** Above this row count an export is queued rather than streamed inline. */
    public const QUEUE_THRESHOLD = 2000;

    public function __construct(private readonly ReportPdf $pdf) {}

    /**
     * A friendly, safe download filename for a report/format.
     */
    public function filename(Report $report, string $format): string
    {
        return $report->key().'-report-'.now()->format('Ymd-His').'.'.$format;
    }

    /**
     * Stream a report straight to the browser (used for < QUEUE_THRESHOLD rows).
     *
     * @param  array<string, mixed>  $filters
     */
    public function download(Report $report, array $filters, string $format): Response
    {
        $filename = $this->filename($report, $format);

        if ($format === 'pdf') {
            return $this->pdf
                ->make($report->label(), $report->headings(), $report->rows($filters), $report->filterSummary($filters))
                ->download($filename);
        }

        return Excel::download(
            new ReportExport($report->label(), $report->headings(), $report->rows($filters)),
            $filename,
            $this->writerType($format),
        );
    }

    /**
     * Stream an ad-hoc set of rows through the same branded export path — used by the
     * Section-6.5 instructor gradebook, so its download shares this one implementation
     * (xlsx/csv/pdf) rather than a bespoke CSV writer.
     *
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, scalar|null>>  $rows
     * @param  array<string, string>  $filterSummary
     */
    public function downloadRows(string $title, array $headings, array $rows, string $format, string $filename, array $filterSummary = []): Response
    {
        if ($format === 'pdf') {
            return $this->pdf->make($title, $headings, $rows, $filterSummary)->download($filename);
        }

        return Excel::download(new ReportExport($title, $headings, $rows), $filename, $this->writerType($format));
    }

    /**
     * Build a queued export's file, persist it to the private disk and stamp the record
     * ready, then notify the requester. Idempotent enough to survive a retry: it just
     * rebuilds the file.
     */
    public function fulfil(GeneratedReport $generated, Report $report): void
    {
        $filters = $generated->filters ?? [];
        $rows = $report->rows($filters);
        $path = 'reports/'.$generated->id.'.'.$generated->format;

        if ($generated->format === 'pdf') {
            $bytes = $this->pdf
                ->make($report->label(), $report->headings(), $rows, $report->filterSummary($filters))
                ->output();
            Storage::disk($generated->disk)->put($path, $bytes);
        } else {
            Excel::store(
                new ReportExport($report->label(), $report->headings(), $rows),
                $path,
                $generated->disk,
                $this->writerType($generated->format),
            );
        }

        $generated->forceFill([
            'path' => $path,
            'row_count' => count($rows),
            'status' => ReportStatus::Ready,
            'ready_at' => now(),
        ])->save();

        $generated->user->notify(new ReportReadyNotification($generated));
    }

    /**
     * Create the tracking record for a queued export (before the job runs).
     *
     * @param  array<string, mixed>  $filters
     */
    public function queue(Report $report, array $filters, string $format, int $userId, int $rowCount): GeneratedReport
    {
        return GeneratedReport::create([
            'user_id' => $userId,
            'report' => $report->key(),
            'format' => $format,
            'title' => $report->label(),
            'filename' => $this->filename($report, $format),
            'disk' => 'private',
            'row_count' => $rowCount,
            'filters' => $filters,
            'status' => ReportStatus::Pending,
        ]);
    }

    private function writerType(string $format): string
    {
        return $format === 'csv' ? ExcelWriter::CSV : ExcelWriter::XLSX;
    }
}
