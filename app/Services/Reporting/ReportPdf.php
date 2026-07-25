<?php

namespace App\Services\Reporting;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;

/**
 * Renders any report-centre report through ONE branded PDF layout (logo header, title,
 * echoed filters, generated-at stamp, page numbers). Given the report's title, columns,
 * fully-materialised rows and a human filter echo, it produces a presentable A4 document
 * — the same rows the preview and spreadsheet show, so all three stay in lock-step.
 */
class ReportPdf
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, scalar|null>>  $rows
     * @param  array<string, string>  $filters
     */
    public function make(string $title, array $headings, array $rows, array $filters): PdfInstance
    {
        // Landscape past a handful of columns, so wide reports still breathe.
        $orientation = count($headings) > 6 ? 'landscape' : 'portrait';

        return Pdf::loadView('reports.pdf.layout', [
            'title' => $title,
            'headings' => $headings,
            'rows' => $rows,
            'filters' => $filters,
            'generatedAt' => now(),
        ])->setPaper('a4', $orientation);
    }
}
