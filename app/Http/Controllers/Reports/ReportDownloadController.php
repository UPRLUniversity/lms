<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\GeneratedReport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a queued report export's file. The file lives on the private disk and is gated
 * by GeneratedReportPolicy — only the person who requested it (or a super-admin) may
 * download it, never a public URL (constitution: generated reports are sensitive).
 */
class ReportDownloadController extends Controller
{
    public function __invoke(GeneratedReport $generatedReport): StreamedResponse
    {
        $this->authorize('download', $generatedReport);

        abort_unless($generatedReport->isReady() && $generatedReport->fileExists(), 404);

        return Storage::disk($generatedReport->disk)->download($generatedReport->path, $generatedReport->filename);
    }
}
