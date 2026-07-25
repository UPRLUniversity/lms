<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The single spreadsheet export behind every report-centre report — Excel and CSV share
 * this class (the writer format is chosen at download time). Rows arrive already mapped to
 * positional cell arrays aligned with the headings, so the sheet never drifts from the
 * on-screen preview. The header row is branded crimson for the xlsx path (styling is a
 * no-op for CSV, which is exactly right).
 */
class ReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        // Sheet names are capped at 31 chars and forbid a handful of characters.
        return \Illuminate\Support\Str::limit(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $this->title), 28, '');
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'C8102E']],
            ],
        ];
    }
}
