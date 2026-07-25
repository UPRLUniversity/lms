<?php

namespace App\Reports;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;

/**
 * Per-enrolment learner report: enrolment, progress, the final grade + grade point taken
 * verbatim from the immutable CourseGradeRecord snapshot (never re-derived, so it matches
 * the gradebook and the printed certificate exactly), and certificate status. A learner
 * with no completed course shows a blank grade — never a misleading 0.
 */
class LearnerReport extends EloquentReport
{
    public function key(): string
    {
        return 'learner';
    }

    public function label(): string
    {
        return 'Learner report';
    }

    public function description(): string
    {
        return 'Enrolment, progress, final grades and certificate status per student.';
    }

    public function icon(): string
    {
        return 'graduation';
    }

    public function headings(): array
    {
        return ['Student', 'Email', 'Course', 'Department', 'Status', 'Progress', 'Final %', 'Grade', 'Grade point', 'Completed', 'Certificate'];
    }

    public function validate(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    public function options(): array
    {
        return [
            'courses' => Course::query()->orderBy('title')->get(['id', 'title', 'code']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    public function filterSummary(array $filters): array
    {
        $summary = [];

        if (! empty($filters['user_id'])) {
            $summary['Student'] = User::find($filters['user_id'])?->name ?? '—';
        }
        if (! empty($filters['course_id'])) {
            $summary['Course'] = Course::find($filters['course_id'])?->title ?? '—';
        }
        if (! empty($filters['department_id'])) {
            $summary['Department'] = Department::find($filters['department_id'])?->name ?? '—';
        }
        if (! empty($filters['date_from'])) {
            $summary['From'] = \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y');
        }
        if (! empty($filters['date_to'])) {
            $summary['To'] = \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d M Y');
        }

        return $summary;
    }

    protected function baseQuery(array $filters): Builder
    {
        return Enrollment::query()
            ->with(['user:id,name,email', 'course:id,title,department_id', 'course.department:id,name'])
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->when(! empty($filters['user_id']), fn (Builder $q) => $q->where('enrollments.user_id', $filters['user_id']))
            ->when(! empty($filters['course_id']), fn (Builder $q) => $q->where('enrollments.course_id', $filters['course_id']))
            ->when(! empty($filters['department_id']), fn (Builder $q) => $q->where('courses.department_id', $filters['department_id']))
            ->when(! empty($filters['date_from']), fn (Builder $q) => $q->where('enrollments.enrolled_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn (Builder $q) => $q->where('enrollments.enrolled_at', '<=', \Illuminate\Support\Carbon::parse($filters['date_to'])->endOfDay()))
            ->orderBy('users.name')
            ->orderBy('courses.title')
            ->select('enrollments.*');
    }

    protected function mapChunk(EloquentCollection $records): array
    {
        // Batch-load the frozen grade snapshots + certificates for this page/chunk,
        // keyed by "user:course", so mapping stays N+1-free at any roster size.
        $userIds = $records->pluck('user_id')->unique();
        $courseIds = $records->pluck('course_id')->unique();

        $recordsByKey = CourseGradeRecord::query()
            ->current()
            ->whereIn('user_id', $userIds)
            ->whereIn('course_id', $courseIds)
            ->get()
            ->keyBy(fn (CourseGradeRecord $r) => $r->user_id.':'.$r->course_id);

        $certsByKey = Certificate::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('course_id', $courseIds)
            ->get()
            ->keyBy(fn (Certificate $c) => $c->user_id.':'.$c->course_id);

        return $records->map(function (Enrollment $e) use ($recordsByKey, $certsByKey): array {
            $key = $e->user_id.':'.$e->course_id;
            $grade = $recordsByKey->get($key);
            $cert = $certsByKey->get($key);

            return [
                $this->cell($e->user?->name),
                $this->cell($e->user?->email),
                $this->cell($e->course?->title),
                $this->cell($e->course?->department?->name),
                $e->status->label(),
                (int) $e->progress_percent.'%',
                $grade ? round((float) $grade->final_percent).'%' : '',
                $grade ? $this->cell($grade->grade_label) : '',
                $grade ? number_format((float) $grade->grade_point, 1) : '',
                $e->completed_at?->format('d M Y') ?? '',
                $cert ? ($cert->isRevoked() ? 'Revoked' : 'Issued') : '',
            ];
        })->all();
    }
}
