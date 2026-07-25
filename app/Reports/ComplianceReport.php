<?php

namespace App\Reports;

use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\User;
use App\Reports\Contracts\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compliance report: for a chosen cohort and a set of target course(s), who has completed,
 * who is in progress and who has never started — with headline percentages. The cohort is
 * either a department (proxied, since users have no direct department, as everyone enrolled
 * in that department's courses — see docs/decisions.md) or a pasted list of e-mails.
 *
 * Rows are the cohort × courses cross-product. The enrolment status for every cell is
 * resolved from a single preloaded map, so there is no per-cell query.
 */
class ComplianceReport implements Report
{
    /** @var array<string, array{users: Collection<int, User>, courses: Collection<int, Course>, map: Collection<string, Enrollment>, unmatched: int}> */
    private array $cache = [];

    public function key(): string
    {
        return 'compliance';
    }

    public function label(): string
    {
        return 'Compliance report';
    }

    public function description(): string
    {
        return 'Completion status of a cohort against required course(s), with percentages.';
    }

    public function icon(): string
    {
        return 'shield';
    }

    public function headings(): array
    {
        return ['Student', 'Email', 'Course', 'Status', 'Progress', 'Completed'];
    }

    public function validate(Request $request): array
    {
        $data = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'cohort' => ['required', 'in:department,emails'],
            'department_id' => ['nullable', 'required_if:cohort,department', 'integer', 'exists:departments,id'],
            'emails' => ['nullable', 'required_if:cohort,emails', 'string'],
        ]);

        return $data;
    }

    public function options(): array
    {
        return [
            'courses' => Course::query()->orderBy('title')->get(['id', 'title', 'code']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function filterSummary(array $filters): array
    {
        $ctx = $this->context($filters);

        $summary = [
            'Courses' => $ctx['courses']->pluck('title')->join(', ') ?: '—',
            'Cohort' => $filters['cohort'] === 'department'
                ? (Department::find($filters['department_id'] ?? null)?->name ?? '—').' (enrolled)'
                : 'E-mail list',
            'People' => (string) $ctx['users']->count(),
        ];

        if ($filters['cohort'] === 'emails' && $ctx['unmatched'] > 0) {
            $summary['Unmatched e-mails'] = (string) $ctx['unmatched'];
        }

        return $summary;
    }

    public function summary(array $filters): array
    {
        $ctx = $this->context($filters);
        $total = $ctx['users']->count() * $ctx['courses']->count();

        if ($total === 0) {
            return [];
        }

        $completed = 0;
        $inProgress = 0;
        foreach ($ctx['users'] as $user) {
            foreach ($ctx['courses'] as $course) {
                $status = $this->statusFor($ctx['map']->get($user->id.':'.$course->id));
                if ($status === 'Completed') {
                    $completed++;
                } elseif ($status === 'In progress') {
                    $inProgress++;
                }
            }
        }
        $neverStarted = $total - $completed - $inProgress;

        $pct = fn (int $n) => round(($n / $total) * 100, 1).'%';

        return [
            ['label' => 'Completed', 'value' => $completed.' · '.$pct($completed), 'tone' => 'success'],
            ['label' => 'In progress', 'value' => $inProgress.' · '.$pct($inProgress), 'tone' => 'crimson'],
            ['label' => 'Never started', 'value' => $neverStarted.' · '.$pct($neverStarted), 'tone' => 'gold'],
        ];
    }

    public function count(array $filters): int
    {
        $ctx = $this->context($filters);

        return $ctx['users']->count() * $ctx['courses']->count();
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $ctx = $this->context($filters);
        $courseCount = $ctx['courses']->count();
        $total = $ctx['users']->count() * $courseCount;

        $page = Paginator::resolveCurrentPage();
        $offset = ($page - 1) * $perPage;

        $items = [];
        for ($i = $offset; $i < min($offset + $perPage, $total); $i++) {
            $user = $ctx['users'][intdiv($i, $courseCount)];
            $course = $ctx['courses'][$i % $courseCount];
            $items[] = $this->row($user, $course, $ctx['map']);
        }

        return new Paginator($items, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }

    public function rows(array $filters): array
    {
        $ctx = $this->context($filters);
        $rows = [];

        foreach ($ctx['users'] as $user) {
            foreach ($ctx['courses'] as $course) {
                $rows[] = $this->row($user, $course, $ctx['map']);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{users: Collection<int, User>, courses: Collection<int, Course>, map: Collection<string, Enrollment>, unmatched: int}
     */
    private function context(array $filters): array
    {
        $signature = md5(serialize($filters));
        if (isset($this->cache[$signature])) {
            return $this->cache[$signature];
        }

        $courses = Course::query()
            ->whereIn('id', $filters['course_ids'] ?? [])
            ->orderBy('title')
            ->get(['id', 'title']);

        $unmatched = 0;

        if (($filters['cohort'] ?? null) === 'emails') {
            $emails = collect(preg_split('/[\s,;]+/', (string) ($filters['emails'] ?? '')))
                ->map(fn (string $e) => strtolower(trim($e)))
                ->filter()
                ->unique()
                ->values();

            $users = User::query()
                ->whereIn(DB::raw('lower(email)'), $emails)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            $unmatched = $emails->count() - $users->count();
        } else {
            // Department cohort: everyone enrolled in a course of that department.
            $userIds = Enrollment::query()
                ->join('courses', 'courses.id', '=', 'enrollments.course_id')
                ->where('courses.department_id', $filters['department_id'] ?? null)
                ->distinct()
                ->pluck('enrollments.user_id');

            $users = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }

        $map = Enrollment::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->whereIn('course_id', $courses->pluck('id'))
            ->get()
            ->keyBy(fn (Enrollment $e) => $e->user_id.':'.$e->course_id);

        return $this->cache[$signature] = [
            'users' => $users->values(),
            'courses' => $courses->values(),
            'map' => $map,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * @param  Collection<string, Enrollment>  $map
     * @return array<int, scalar|null>
     */
    private function row(User $user, Course $course, Collection $map): array
    {
        $enrollment = $map->get($user->id.':'.$course->id);
        $status = $this->statusFor($enrollment);

        return [
            $user->name,
            $user->email,
            $course->title,
            $status,
            $enrollment ? (int) $enrollment->progress_percent.'%' : '',
            $enrollment?->completed_at?->format('d M Y') ?? '',
        ];
    }

    private function statusFor(?Enrollment $enrollment): string
    {
        if ($enrollment === null) {
            return 'Never started';
        }

        return $enrollment->isComplete() ? 'Completed' : 'In progress';
    }
}
