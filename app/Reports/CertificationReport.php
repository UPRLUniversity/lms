<?php

namespace App\Reports;

use App\Models\Certificate;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Certification report: certificates issued/revoked in a date range, optionally by course,
 * with the grade shown exactly as it was printed on the certificate (from the frozen
 * snapshot — blank where the certificate carried no grade line).
 */
class CertificationReport extends EloquentReport
{
    public function key(): string
    {
        return 'certification';
    }

    public function label(): string
    {
        return 'Certification report';
    }

    public function description(): string
    {
        return 'Certificates issued and revoked, by course and date, with printed grade.';
    }

    public function icon(): string
    {
        return 'certificate';
    }

    public function headings(): array
    {
        return ['Serial', 'Student', 'Course', 'Issued', 'Status', 'Revoked', 'Grade'];
    }

    public function validate(Request $request): array
    {
        return $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'status' => ['nullable', 'in:all,active,revoked'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    public function options(): array
    {
        return [
            'courses' => Course::query()->orderBy('title')->get(['id', 'title', 'code']),
            'statuses' => ['all' => 'All', 'active' => 'Active', 'revoked' => 'Revoked'],
        ];
    }

    public function filterSummary(array $filters): array
    {
        $summary = [];

        if (! empty($filters['course_id'])) {
            $summary['Course'] = Course::find($filters['course_id'])?->title ?? '—';
        }
        $summary['Status'] = ucfirst($filters['status'] ?? 'all');
        if (! empty($filters['date_from'])) {
            $summary['From'] = Carbon::parse($filters['date_from'])->format('d M Y');
        }
        if (! empty($filters['date_to'])) {
            $summary['To'] = Carbon::parse($filters['date_to'])->format('d M Y');
        }

        return $summary;
    }

    public function summary(array $filters): array
    {
        $base = $this->scopedQuery($filters);

        $issued = (clone $base)->count();
        $revoked = (clone $base)->whereNotNull('revoked_at')->count();

        return [
            ['label' => 'In range', 'value' => number_format($issued), 'tone' => 'crimson'],
            ['label' => 'Active', 'value' => number_format($issued - $revoked), 'tone' => 'success'],
            ['label' => 'Revoked', 'value' => number_format($revoked), 'tone' => 'gold'],
        ];
    }

    protected function baseQuery(array $filters): Builder
    {
        return $this->scopedQuery($filters)
            ->with(['user:id,name', 'course:id,title'])
            ->orderByDesc('issued_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Certificate>
     */
    private function scopedQuery(array $filters): Builder
    {
        return Certificate::query()
            ->when(! empty($filters['course_id']), fn (Builder $q) => $q->where('course_id', $filters['course_id']))
            ->when(($filters['status'] ?? 'all') === 'active', fn (Builder $q) => $q->whereNull('revoked_at'))
            ->when(($filters['status'] ?? 'all') === 'revoked', fn (Builder $q) => $q->whereNotNull('revoked_at'))
            ->when(! empty($filters['date_from']), fn (Builder $q) => $q->where('issued_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn (Builder $q) => $q->where('issued_at', '<=', Carbon::parse($filters['date_to'])->endOfDay()));
    }

    protected function mapChunk(EloquentCollection $records): array
    {
        return $records->map(fn (Certificate $c): array => [
            $c->serial,
            $this->cell($c->user?->name),
            $this->cell($c->course?->title),
            $c->issued_at?->format('d M Y') ?? '',
            $c->isRevoked() ? 'Revoked' : 'Active',
            $c->revoked_at?->format('d M Y') ?? '',
            $this->cell($c->gradeLine()),
        ])->all();
    }
}
