<?php

namespace App\Services\Reporting;

use App\Enums\AssignmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\SubmissionStatus;
use App\Models\Attempt;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregation behind the role-aware dashboards. Every figure is a real query
 * against seeded/live data — never a hard-coded placeholder. The platform-wide admin
 * numbers (which sweep the whole enrollment/attempt tables) are memoised for five
 * minutes; per-user instructor/student views are cheap and read live so a learner never
 * sees a stale progress bar.
 */
class DashboardService
{
    private const CACHE_TTL = 300; // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | Admin / auditor — whole platform
    |--------------------------------------------------------------------------
    */

    /**
     * The admin stat cards. Cached because each sweeps a full table; the
     * spot-checkable figures still reconcile exactly with the report centre.
     *
     * @return array<string, int|float>
     */
    public function adminStats(): array
    {
        return Cache::remember('dashboard:admin:stats', self::CACHE_TTL, function (): array {
            // One grouped pass over enrollments gives every status bucket.
            $byStatus = Enrollment::query()
                ->select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $active = (int) $byStatus->get(EnrollmentStatus::Active->value, 0);
            $completed = (int) $byStatus->get(EnrollmentStatus::Completed->value, 0);
            $withdrawn = (int) $byStatus->get(EnrollmentStatus::Withdrawn->value, 0);
            $total = (int) $byStatus->sum();

            // Of every student who ever took a seat (active + completed + withdrawn),
            // the share who finished — a truer completion rate than completed ÷ all,
            // which pending/rejected applications would drag down.
            $seated = $active + $completed + $withdrawn;
            $completionRate = $seated > 0 ? round(($completed / $seated) * 100, 1) : 0.0;

            return [
                'activeUsers30d' => (int) User::query()
                    ->where('last_login_at', '>=', now()->subDays(30))
                    ->count(),
                'totalEnrollments' => $total,
                'activeEnrollments' => $active,
                'completionRate' => $completionRate,
                'certificatesIssued' => (int) Certificate::query()->active()->count(),
                'pendingReviews' => (int) Course::query()
                    ->where('status', CourseStatus::Review->value)->count(),
                'pendingApprovals' => (int) $byStatus->get(EnrollmentStatus::Pending->value, 0),
                'gradingQueue' => (int) Submission::query()
                    ->where('status', SubmissionStatus::Submitted->value)->count()
                    + (int) Attempt::query()
                        ->where('status', AttemptStatus::Submitted->value)->count(),
            ];
        });
    }

    /**
     * Enrollments per month for the last 12 calendar months (oldest → newest), zero-filled
     * so the trend line never skips an empty month. One grouped, driver-portable query.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function enrollmentTrend(): array
    {
        return Cache::remember('dashboard:admin:trend', self::CACHE_TTL, function (): array {
            $start = now()->startOfMonth()->subMonths(11);

            $counts = Enrollment::query()
                ->where('enrolled_at', '>=', $start)
                ->select(DB::raw($this->monthExpression('enrolled_at').' as ym'), DB::raw('count(*) as aggregate'))
                ->groupBy('ym')
                ->pluck('aggregate', 'ym');

            $labels = [];
            $values = [];
            for ($i = 0; $i < 12; $i++) {
                $month = (clone $start)->addMonths($i);
                $key = $month->format('Y-m');
                $labels[] = $month->format('M Y');
                $values[] = (int) $counts->get($key, 0);
            }

            return ['labels' => $labels, 'values' => $values];
        });
    }

    /**
     * The most-enrolled published courses, capped, with their live enrollment counts.
     *
     * @return Collection<int, array{course: Course, enrollments: int}>
     */
    public function topCourses(int $limit = 5): Collection
    {
        return Cache::remember('dashboard:admin:top-courses:'.$limit, self::CACHE_TTL, function () use ($limit): Collection {
            $counts = Enrollment::query()
                ->select('course_id', DB::raw('count(*) as aggregate'))
                ->groupBy('course_id')
                ->orderByDesc('aggregate')
                ->limit($limit)
                ->pluck('aggregate', 'course_id');

            if ($counts->isEmpty()) {
                return collect();
            }

            $courses = Course::query()
                ->with('department')
                ->whereIn('id', $counts->keys())
                ->get()
                ->keyBy('id');

            return $counts
                ->map(fn (int $count, int $courseId) => [
                    'course' => $courses->get($courseId),
                    'enrollments' => (int) $count,
                ])
                ->filter(fn (array $row) => $row['course'] !== null)
                ->values();
        });
    }

    /**
     * A recent-activity feed drawn from the enrollment ledger — the same row covers a
     * new enrolment and, once its status advances, a completion. Eager-loaded, capped,
     * and read live (an activity feed that lags five minutes reads as broken).
     *
     * @return Collection<int, array{icon: string, tone: string, text: string, when: Carbon}>
     */
    public function recentActivity(int $limit = 8): Collection
    {
        $enrollments = Enrollment::query()
            ->with(['user:id,name', 'course:id,title,slug'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();

        return $enrollments->map(function (Enrollment $e): array {
            $completed = $e->status === EnrollmentStatus::Completed;

            return [
                'icon' => $completed ? 'check-circle' : 'user-plus',
                'tone' => $completed ? 'success' : 'crimson',
                'text' => $completed
                    ? "{$e->user?->name} completed {$e->course?->title}"
                    : "{$e->user?->name} · ".$e->status->label()." in {$e->course?->title}",
                'when' => $e->completed_at ?? $e->updated_at,
            ];
        })->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Instructor — their own courses
    |--------------------------------------------------------------------------
    */

    /**
     * The instructor overview: their courses (with live enrollment counts) plus the
     * three headline figures and the ungraded backlog that deep-links into the queues.
     *
     * @return array{courses: Collection<int, Course>, stats: array<string, int|float|null>}
     */
    public function instructorOverview(User $user): array
    {
        $courses = Course::forInstructor($user)
            ->withCount([
                'enrollments as active_count' => fn ($q) => $q->where('status', EnrollmentStatus::Active->value),
                'enrollments as completed_count' => fn ($q) => $q->where('status', EnrollmentStatus::Completed->value),
            ])
            ->orderBy('title')
            ->get();

        $courseIds = $courses->pluck('id');

        if ($courseIds->isEmpty()) {
            return [
                'courses' => $courses,
                'stats' => [
                    'totalEnrollments' => 0, 'averageProgress' => null,
                    'averageScore' => null, 'ungraded' => 0,
                ],
            ];
        }

        $totalEnrollments = (int) Enrollment::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->count();

        $averageProgress = Enrollment::query()
            ->whereIn('course_id', $courseIds)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->avg('progress_percent');

        $averageScore = Attempt::query()
            ->whereIn('assessment_id', fn ($q) => $q->select('id')->from('assessments')->whereIn('course_id', $courseIds))
            ->where('status', AttemptStatus::Graded->value)
            ->avg('percentage');

        $ungradedSubmissions = (int) Submission::query()
            ->whereIn('assignment_id', fn ($q) => $q->select('id')->from('assignments')->whereIn('course_id', $courseIds))
            ->where('status', SubmissionStatus::Submitted->value)
            ->count();

        $ungradedAttempts = (int) Attempt::query()
            ->whereIn('assessment_id', fn ($q) => $q->select('id')->from('assessments')->whereIn('course_id', $courseIds))
            ->where('status', AttemptStatus::Submitted->value)
            ->count();

        return [
            'courses' => $courses,
            'stats' => [
                'totalEnrollments' => $totalEnrollments,
                'averageProgress' => $averageProgress !== null ? round((float) $averageProgress, 1) : null,
                'averageScore' => $averageScore !== null ? round((float) $averageScore, 1) : null,
                'ungraded' => $ungradedSubmissions + $ungradedAttempts,
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Student — their own learning
    |--------------------------------------------------------------------------
    */

    /**
     * Everything the student dashboard renders: continue-learning cards, the progress
     * overview, upcoming assignment due dates, recent grades (badged through the
     * governing scale) and their certificates. Read live.
     *
     * @return array<string, mixed>
     */
    public function studentDashboard(User $user): array
    {
        $enrollments = $user->enrollments()
            ->with(['course.department', 'course.media'])
            ->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Waitlisted->value,
                EnrollmentStatus::Completed->value,
            ])
            ->get();

        $active = $enrollments->where('status', EnrollmentStatus::Active);
        $activeCourseIds = $active->pluck('course_id');

        $stats = [
            'inProgress' => $active->count(),
            'completed' => $enrollments->where('status', EnrollmentStatus::Completed)->count(),
            'awaiting' => $enrollments->whereIn('status', [EnrollmentStatus::Pending, EnrollmentStatus::Waitlisted])->count(),
            'averageProgress' => $active->isNotEmpty() ? (int) round($active->avg('progress_percent')) : 0,
        ];

        $continueLearning = $active->sortByDesc('enrolled_at')->take(3)->values();

        // Upcoming, still-open assignment deadlines across the student's active courses.
        $upcoming = $activeCourseIds->isEmpty() ? collect() : \App\Models\Assignment::query()
            ->with('course:id,title,slug')
            ->whereIn('course_id', $activeCourseIds)
            ->where('status', AssignmentStatus::Published->value)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', now())
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        $recentGrades = $this->recentGradesFor($user);

        $certificates = $user->certificates()
            ->with('course:id,title,slug')
            ->active()
            ->latest('issued_at')
            ->limit(4)
            ->get();

        return [
            'stats' => $stats,
            'continueLearning' => $continueLearning,
            'upcoming' => $upcoming,
            'recentGrades' => $recentGrades,
            'certificates' => $certificates,
        ];
    }

    /**
     * The student's most recent graded results (assessment attempts + assignment
     * submissions), newest first, each badged through its course's governing scale.
     *
     * @return Collection<int, array{title: string, course: string, percent: int, label: ?string, color: ?string, when: Carbon}>
     */
    private function recentGradesFor(User $user, int $limit = 6): Collection
    {
        $attempts = Attempt::query()
            ->with('assessment.course')
            ->where('user_id', $user->id)
            ->where('status', AttemptStatus::Graded->value)
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Attempt $a) => [
                'title' => $a->assessment?->title ?? 'Assessment',
                'course' => $a->assessment?->course,
                'percent' => (int) round((float) $a->percentage),
                'when' => $a->updated_at,
            ]);

        $submissions = Submission::query()
            ->with(['assignment.course', 'grade'])
            ->where('user_id', $user->id)
            ->where('status', SubmissionStatus::Graded->value)
            ->latest('updated_at')
            ->limit($limit)
            ->get()
            ->filter(fn (Submission $s) => $s->grade !== null && (float) ($s->assignment->max_points ?? 0) > 0)
            ->map(fn (Submission $s) => [
                'title' => $s->assignment?->title ?? 'Assignment',
                'course' => $s->assignment?->course,
                'percent' => (int) round(((float) $s->grade->points_total / (float) $s->assignment->max_points) * 100),
                'when' => $s->updated_at,
            ]);

        // Resolve each distinct course's scale once, then badge every row.
        $courses = $attempts->merge($submissions)->pluck('course')->filter()->unique('id');
        $bands = $courses->mapWithKeys(function (Course $course) {
            $scale = $course->gradeScaleOrDefault();
            $scale?->loadMissing('bands');

            return [$course->id => $scale];
        });

        return $attempts->merge($submissions)
            ->filter(fn (array $row) => $row['course'] !== null)
            ->sortByDesc('when')
            ->take($limit)
            ->map(function (array $row) use ($bands): array {
                $band = $bands->get($row['course']->id)?->bandFor($row['percent']);

                return [
                    'title' => $row['title'],
                    'course' => $row['course']->title,
                    'percent' => $row['percent'],
                    'label' => $band?->label,
                    'color' => $band?->color,
                    'when' => $row['when'],
                ];
            })
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * A driver-portable "year-month" SQL expression, so the trend query stays a single
     * grouped statement on SQLite (tests), MySQL/MariaDB and PostgreSQL alike.
     */
    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', $column)",
            'pgsql' => "to_char($column, 'YYYY-MM')",
            default => "DATE_FORMAT($column, '%Y-%m')",
        };
    }
}
