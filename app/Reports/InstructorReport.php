<?php

namespace App\Reports;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use App\Reports\Contracts\Report;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Instructor report: one row per teaching staff member — their courses, total
 * enrolments, completion rate and median grading turnaround (submitted → graded). Rows
 * are computed in memory: the teaching roster is small, and the heavy per-course figures
 * are preloaded in a handful of grouped queries rather than per instructor.
 */
class InstructorReport implements Report
{
    /** @var array<string, Collection<int, array<int, scalar|null>>> memoised per filter set */
    private array $cache = [];

    public function key(): string
    {
        return 'instructor';
    }

    public function label(): string
    {
        return 'Instructor report';
    }

    public function description(): string
    {
        return 'Courses, enrolments, completion rates and grading turnaround per instructor.';
    }

    public function icon(): string
    {
        return 'pencil';
    }

    public function headings(): array
    {
        return ['Instructor', 'Email', 'Courses', 'Enrolments', 'Completion rate', 'Median turnaround'];
    }

    public function validate(Request $request): array
    {
        return $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    public function options(): array
    {
        return ['departments' => Department::query()->orderBy('name')->get(['id', 'name'])];
    }

    public function filterSummary(array $filters): array
    {
        $summary = [];

        if (! empty($filters['department_id'])) {
            $summary['Department'] = Department::find($filters['department_id'])?->name ?? '—';
        }
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
        return [];
    }

    public function count(array $filters): int
    {
        return $this->build($filters)->count();
    }

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $rows = $this->build($filters);
        $page = Paginator::resolveCurrentPage();
        $items = $rows->forPage($page, $perPage)->values();

        return new Paginator($items, $rows->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }

    public function rows(array $filters): array
    {
        return $this->build($filters)->all();
    }

    /**
     * Build every instructor row for the filter set (memoised). A few grouped queries
     * feed all instructors; nothing runs per-instructor in a loop.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<int, scalar|null>>
     */
    private function build(array $filters): Collection
    {
        $signature = md5(serialize($filters));
        if (isset($this->cache[$signature])) {
            return $this->cache[$signature];
        }

        $departmentId = $filters['department_id'] ?? null;

        // Course → instructor ids (creator + explicit co-instructors), scoped by department.
        $courses = Course::query()
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->get(['id', 'created_by']);
        $courseIds = $courses->pluck('id');

        $pivot = DB::table('course_instructor')
            ->whereIn('course_id', $courseIds)
            ->get(['course_id', 'user_id']);

        // instructor_id => [course ids they teach]
        $coursesByInstructor = [];
        foreach ($courses as $course) {
            if ($course->created_by) {
                $coursesByInstructor[$course->created_by][] = $course->id;
            }
        }
        foreach ($pivot as $row) {
            $coursesByInstructor[$row->user_id][] = $row->course_id;
        }
        $coursesByInstructor = array_map(fn (array $ids) => array_values(array_unique($ids)), $coursesByInstructor);

        // Enrolment totals + completions per course, one grouped query (date-scoped).
        $enrolAgg = \App\Models\Enrollment::query()
            ->whereIn('course_id', $courseIds)
            ->when(! empty($filters['date_from']), fn ($q) => $q->where('enrolled_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->where('enrolled_at', '<=', Carbon::parse($filters['date_to'])->endOfDay()))
            ->select('course_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed', [EnrollmentStatus::Completed->value])
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        // Grading turnaround samples (hours) per course, one query joining the grade ledger.
        $turnaround = DB::table('grades')
            ->join('submissions', 'submissions.id', '=', 'grades.submission_id')
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->where('submissions.status', SubmissionStatus::Graded->value)
            ->whereIn('assignments.course_id', $courseIds)
            ->when(! empty($filters['date_from']), fn ($q) => $q->where('submissions.submitted_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($q) => $q->where('submissions.submitted_at', '<=', Carbon::parse($filters['date_to'])->endOfDay()))
            ->get(['assignments.course_id', 'submissions.submitted_at', 'grades.graded_at']);

        $hoursByCourse = [];
        foreach ($turnaround as $t) {
            if ($t->submitted_at && $t->graded_at) {
                $hoursByCourse[$t->course_id][] = Carbon::parse($t->submitted_at)->diffInMinutes(Carbon::parse($t->graded_at)) / 60;
            }
        }

        $instructors = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', Role::Instructor->value))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $rows = $instructors
            ->map(function (User $instructor) use ($coursesByInstructor, $enrolAgg, $hoursByCourse): ?array {
                $ids = $coursesByInstructor[$instructor->id] ?? [];
                if ($ids === []) {
                    return null; // Teaches nothing in scope — omit from the report.
                }

                $total = 0;
                $completed = 0;
                $hours = [];
                foreach ($ids as $courseId) {
                    $agg = $enrolAgg->get($courseId);
                    $total += (int) ($agg->total ?? 0);
                    $completed += (int) ($agg->completed ?? 0);
                    foreach ($hoursByCourse[$courseId] ?? [] as $h) {
                        $hours[] = $h;
                    }
                }

                $rate = $total > 0 ? round(($completed / $total) * 100, 1).'%' : '0%';

                return [
                    $instructor->name,
                    $instructor->email,
                    (string) count($ids),
                    (string) $total,
                    $rate,
                    $this->formatTurnaround($hours),
                ];
            })
            ->filter()
            ->values();

        return $this->cache[$signature] = $rows;
    }

    /**
     * Median of a set of hour-durations, humanised — blank when nothing has been graded.
     *
     * @param  array<int, float>  $hours
     */
    private function formatTurnaround(array $hours): string
    {
        if ($hours === []) {
            return '';
        }

        sort($hours);
        $count = count($hours);
        $mid = intdiv($count, 2);
        $median = $count % 2 === 0 ? ($hours[$mid - 1] + $hours[$mid]) / 2 : $hours[$mid];

        return $median >= 48
            ? round($median / 24, 1).' days'
            : round($median, 1).'h';
    }
}
