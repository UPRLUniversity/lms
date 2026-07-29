<?php

namespace App\Http\Controllers\Grades;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\User;
use App\Services\Grades\CourseGradeRecordService;
use App\Services\Grades\GradebookService;
use App\Services\Reporting\ReportExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The gradebook: a student's own "Grades" tab and the instructor's per-course matrix.
 * Both read through GradebookService (pure) — nothing here scores anything, it only
 * aggregates + displays what Sections 5/6 already graded.
 */
class GradebookController extends Controller
{
    public function __construct(private readonly GradebookService $gradebook) {}

    /**
     * A student's own course grades: item table + summary card. Once the enrollment has
     * completed, the summary shows the immutable CourseGradeRecord rather than a figure
     * re-derived from whatever the live scale currently says.
     */
    public function mine(Request $request, Course $course): View
    {
        $this->authorize('viewOwnGradebook', $course);

        $user = $request->user();
        $scale = $course->gradeScaleOrDefault();

        $items = $scale ? $this->gradebook->itemsFor($user, $course) : collect();
        $summary = $scale ? $this->gradebook->summarize($items, $scale) : null;

        $record = CourseGradeRecord::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->current()
            ->first();

        return view('grades.mine', [
            'course' => $course,
            'scale' => $scale,
            'items' => $items,
            'summary' => $summary,
            'record' => $record,
        ]);
    }

    /**
     * The instructor matrix: students × gradebook items, one row summary per student,
     * a class grade-distribution mini-chart. Batched (GradebookService::itemsForMany)
     * so it stays N+1-free regardless of roster size.
     */
    public function matrix(Request $request, Course $course): View
    {
        $this->authorize('viewGradebookMatrix', $course);

        $scale = $course->gradeScaleOrDefault();

        $students = $course->enrollments()
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->with('user')
            ->get()
            ->pluck('user')
            ->sortBy('name')
            ->values();

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $students = $students->filter(fn (User $u) => str_contains(strtolower($u->name), strtolower($search))
                || str_contains(strtolower($u->email), strtolower($search)))->values();
        }

        $itemsByUser = $scale ? $this->gradebook->itemsForMany($students, $course) : collect();

        $rows = $students->map(function (User $student) use ($itemsByUser, $scale) {
            $items = $itemsByUser->get($student->id, collect());
            $summary = $scale ? $this->gradebook->summarize($items, $scale) : null;

            return [
                'user' => $student,
                'items' => $items,
                'summary' => $summary,
                'record' => null,
            ];
        });

        // Records for completed students, one query, keyed by user id.
        $recordsByUser = CourseGradeRecord::query()
            ->where('course_id', $course->id)
            ->whereIn('user_id', $students->pluck('id'))
            ->current()
            ->get()
            ->keyBy('user_id');

        $rows = $rows->map(function (array $row) use ($recordsByUser) {
            $row['record'] = $recordsByUser->get($row['user']->id);

            return $row;
        });

        // Whitelisted sort — 'grade' orders by the live percentage (nulls last), else name.
        $sort = $request->query('sort') === 'grade' ? 'grade' : 'name';
        $rows = $sort === 'grade'
            ? $rows->sortByDesc(fn (array $r) => $r['summary']?->percent ?? -1)->values()
            : $rows->sortBy(fn (array $r) => strtolower($r['user']->name))->values();

        // Class distribution: count of students per band (brand palette, current scale).
        $distribution = collect();
        if ($scale) {
            $scale->loadMissing('bands');
            $distribution = $scale->bands->map(fn ($band) => [
                'band' => $band,
                'count' => $rows->filter(fn (array $r) => $r['summary']?->band?->is($band))->count(),
            ]);
        }

        return view('grades.matrix', [
            'course' => $course,
            'scale' => $scale,
            'rows' => $rows,
            'search' => $search,
            'sort' => $sort,
            'distribution' => $distribution,
            'canRecompute' => Gate::forUser($request->user())->allows('recompute-gradebook'),
            'itemCount' => $rows->isEmpty() ? 0 : $rows->first()['items']->count(),
        ]);
    }

    /**
     * The gradebook summary export — one row per active/completed student. Wired into the
     * Section-10 unified export path (ReportExporter), so it produces the same branded
     * xlsx/csv/pdf as the report centre rather than a bespoke CSV writer. Format defaults
     * to CSV to preserve the old link's behaviour.
     */
    public function export(Request $request, Course $course, ReportExporter $exporter): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('viewGradebookMatrix', $course);

        $format = in_array($request->query('format'), ['xlsx', 'csv', 'pdf'], true) ? $request->query('format') : 'csv';
        $scale = $course->gradeScaleOrDefault();

        $students = $course->enrollments()
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->with('user')
            ->get()
            ->pluck('user')
            ->sortBy('name')
            ->values();

        $itemsByUser = $scale ? $this->gradebook->itemsForMany($students, $course) : collect();

        $rows = $students->map(function (User $student) use ($itemsByUser, $scale) {
            $summary = $scale ? $this->gradebook->summarize($itemsByUser->get($student->id, collect()), $scale) : null;

            return [
                $student->name,
                $student->email,
                $summary?->percent !== null ? round($summary->percent).'%' : '',
                $summary?->gradeLabel() ?? '',
                $summary?->gradePoint() !== null ? number_format($summary->gradePoint(), 1) : '',
                $summary === null || ! $summary->hasItems() ? 'No gradable items' : ($summary->provisional ? 'Provisional' : 'Final'),
            ];
        })->all();

        return $exporter->downloadRows(
            'Gradebook · '.$course->title,
            ['Name', 'Email', 'Percentage', 'Grade', 'Grade point', 'Status'],
            $rows,
            $format,
            'gradebook-'.$course->slug.'-'.now()->format('Ymd').'.'.$format,
            ['Course' => $course->title],
        );
    }

    /**
     * Admin-only re-issue: recomputes a student's completion snapshot against the
     * course's current scale, superseding (never editing) their existing record.
     */
    public function recompute(Course $course, User $user, CourseGradeRecordService $records): RedirectResponse
    {
        $this->authorize('recompute-gradebook');

        $record = $records->recompute($course, $user);

        return back()->with($record
            ? ['status' => "Recomputed {$user->name}'s grade."]
            : ['error' => "{$user->name} has no final gradable result yet — nothing to recompute."]);
    }
}
