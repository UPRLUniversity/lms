<?php

namespace App\Reports\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * A report in the Section-10 report centre. One implementation per report drives the
 * whole triad — the on-screen preview, and the Excel/CSV/PDF exports — from a single
 * source of truth, so what a Vice-Chancellor prints matches exactly what the admin saw.
 *
 * A "row" is a positional array of scalar cell values aligned 1:1 with headings(), so
 * the preview table, the spreadsheet and the PDF never drift out of column order.
 */
interface Report
{
    /** Stable url/registry key, e.g. "learner". */
    public function key(): string;

    /** Human title, e.g. "Learner report". */
    public function label(): string;

    /** One-line description for the report-centre landing card. */
    public function description(): string;

    /** <x-ui.icon> name for the landing card. */
    public function icon(): string;

    /**
     * Ordered column headings.
     *
     * @return array<int, string>
     */
    public function headings(): array;

    /**
     * Validate + normalise the request's filter inputs into a plain array the other
     * methods consume. Throws a ValidationException on bad input.
     *
     * @return array<string, mixed>
     */
    public function validate(Request $request): array;

    /**
     * Select options for the filter form (courses, departments, users …).
     *
     * @return array<string, mixed>
     */
    public function options(): array;

    /**
     * Human-readable echo of the applied filters, for the PDF header and the preview
     * caption, e.g. ['Course' => 'Media Law', 'From' => '01 Jan 2026'].
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function filterSummary(array $filters): array;

    /**
     * Optional headline metrics shown above the table (compliance %s, etc.).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array{label: string, value: string, tone?: string}>
     */
    public function summary(array $filters): array;

    /**
     * Total number of rows the current filters yield — the basis of the >2k queued
     * export decision.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters): int;

    /**
     * A single page of mapped rows for the on-screen preview.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<int, scalar|null>>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * Every mapped row, for export. Materialised in one pass — large exports run this
     * inside a queued job, never a web request.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, scalar|null>>
     */
    public function rows(array $filters): array;
}
