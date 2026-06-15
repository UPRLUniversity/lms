<?php

namespace App\Exports;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * A course's roster as a downloadable spreadsheet (CSV/xlsx via maatwebsite/excel).
 * One row per enrollment with the human-meaningful fields a coordinator needs.
 */
class RosterExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Course $course) {}

    /**
     * @return Collection<int, Enrollment>
     */
    public function collection(): Collection
    {
        return $this->course->enrollments()
            ->with(['user', 'approver'])
            ->orderBy('status')
            ->orderBy('enrolled_at')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Name', 'Email', 'Status', 'Source', 'Enrolled at', 'Approved by'];
    }

    /**
     * @param  Enrollment  $enrollment
     * @return array<int, string>
     */
    public function map($enrollment): array
    {
        return [
            $enrollment->user?->name ?? '—',
            $enrollment->user?->email ?? '—',
            $enrollment->status->label(),
            $enrollment->source->label(),
            $enrollment->enrolled_at?->format('Y-m-d H:i') ?? '—',
            $enrollment->approver?->name ?? '',
        ];
    }
}
