<?php

namespace App\Exports;

use App\Support\Grades\GradebookSummary;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * A course's gradebook summary as a downloadable spreadsheet — one row per student.
 * The full per-item breakdown stays in-app; this is the simple summary export the
 * section calls for (the Section 10 report centre gets the fuller export later).
 */
class GradebookExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, array{user: \App\Models\User, summary: ?GradebookSummary}>  $rows
     */
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Name', 'Email', 'Percentage', 'Grade', 'Grade point', 'Status'];
    }

    /**
     * @param  array{user: \App\Models\User, summary: ?GradebookSummary}  $row
     * @return array<int, string>
     */
    public function map($row): array
    {
        $summary = $row['summary'];

        return [
            $row['user']->name,
            $row['user']->email,
            $summary?->percent !== null ? round($summary->percent).'%' : '—',
            $summary?->gradeLabel() ?? '—',
            $summary?->gradePoint() !== null ? number_format($summary->gradePoint(), 1) : '—',
            $summary === null || ! $summary->hasItems() ? 'No gradable items' : ($summary->provisional ? 'Provisional' : 'Final'),
        ];
    }
}
