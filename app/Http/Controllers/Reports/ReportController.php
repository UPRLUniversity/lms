<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExport;
use App\Reports\ReportRegistry;
use App\Services\Reporting\ReportExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The report centre (Section 10) — admin + read-only auditor. Route middleware gates the
 * whole area on the reports.view permission (auditors hold it; the export action never
 * mutates anything, so read-only access is safe). Each report drives its own preview and
 * all three export formats from one implementation via the ReportRegistry.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly ReportExporter $exporter,
    ) {}

    public function index(): View
    {
        return view('reports.index', ['reports' => $this->registry->all()]);
    }

    public function show(Request $request, string $report): View
    {
        $definition = $this->registry->findOrFail($report);

        $applied = $request->has('apply');
        $filters = [];
        $results = null;
        $summary = [];

        if ($applied) {
            $filters = $definition->validate($request);
            $results = $definition->paginate($filters);
            $summary = $definition->summary($filters);
        }

        return view('reports.show', [
            'report' => $definition,
            'options' => $definition->options(),
            'filters' => $filters,
            'filterSummary' => $applied ? $definition->filterSummary($filters) : [],
            'results' => $results,
            'summary' => $summary,
            'applied' => $applied,
        ]);
    }

    /**
     * Export the filtered report. Under the queue threshold it streams straight back;
     * over it, the build is handed to a queued job and the requester is told an in-app
     * notification will carry the download link (Section 8 infrastructure).
     */
    public function export(Request $request, string $report): Response|RedirectResponse
    {
        $definition = $this->registry->findOrFail($report);

        $format = $request->validate(['format' => ['required', 'in:xlsx,csv,pdf']])['format'];
        $filters = $definition->validate($request);

        if ($definition->count($filters) > ReportExporter::queueThreshold()) {
            $generated = $this->exporter->queue($definition, $filters, $format, $request->user()->id, $definition->count($filters));
            GenerateReportExport::dispatch($generated->id);

            return back()->with('status', "Your report is being prepared — we'll send you a notification with the download link when it's ready.");
        }

        return $this->exporter->download($definition, $filters, $format);
    }
}
