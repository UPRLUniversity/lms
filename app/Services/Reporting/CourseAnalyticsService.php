<?php

namespace App\Services\Reporting;

use App\Enums\AssessmentPlacement;
use App\Enums\AssessmentStatus;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Services\Grades\GradebookService;
use App\Support\Grades\GradebookSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The instructor's per-course drill-down: how far the cohort has progressed, how the
 * assessments are performing (average, pass rate, the questions tripping students up),
 * the pre → post knowledge gain, and the Section-6.5 class grade distribution. Pure
 * reads over the Section 5/6/6.5 engines — nothing here scores or mutates.
 */
class CourseAnalyticsService
{
    public function __construct(private readonly GradebookService $gradebook) {}

    /**
     * Buckets the roster's progress into presentable bands for a distribution chart.
     * A single query (progress lives on the enrollment row).
     *
     * @return array{labels: array<int, string>, values: array<int, int>, total: int}
     */
    public function progressDistribution(Course $course): array
    {
        $percents = Enrollment::query()
            ->where('course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->pluck('progress_percent');

        $buckets = ['Not started' => 0, '1–25%' => 0, '26–50%' => 0, '51–75%' => 0, '76–99%' => 0, 'Complete' => 0];

        foreach ($percents as $p) {
            $p = (int) $p;
            $key = match (true) {
                $p <= 0 => 'Not started',
                $p <= 25 => '1–25%',
                $p <= 50 => '26–50%',
                $p <= 75 => '51–75%',
                $p < 100 => '76–99%',
                default => 'Complete',
            };
            $buckets[$key]++;
        }

        return [
            'labels' => array_keys($buckets),
            'values' => array_values($buckets),
            'total' => $percents->count(),
        ];
    }

    /**
     * Per-assessment performance for every published assessment in the course: attempts,
     * average score and pass rate. Two grouped queries regardless of assessment count.
     *
     * @return Collection<int, array{assessment: Assessment, attempts: int, average: ?float, passRate: ?float, placement: AssessmentPlacement}>
     */
    public function assessmentStats(Course $course): Collection
    {
        $assessments = $course->assessments()
            ->where('status', AssessmentStatus::Published->value)
            ->orderBy('position')
            ->get();

        if ($assessments->isEmpty()) {
            return collect();
        }

        $agg = Attempt::query()
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->where('status', AttemptStatus::Graded->value)
            ->select('assessment_id')
            ->selectRaw('count(*) as attempts')
            ->selectRaw('avg(percentage) as average')
            ->selectRaw('avg(case when passed = 1 then 1.0 else 0.0 end) * 100 as pass_rate')
            ->groupBy('assessment_id')
            ->get()
            ->keyBy('assessment_id');

        return $assessments->map(function (Assessment $a) use ($agg) {
            $row = $agg->get($a->id);

            return [
                'assessment' => $a,
                'placement' => $a->placement,
                'attempts' => (int) ($row->attempts ?? 0),
                'average' => $row && $row->average !== null ? round((float) $row->average, 1) : null,
                'passRate' => $row && $row->pass_rate !== null ? round((float) $row->pass_rate, 1) : null,
            ];
        })->values();
    }

    /**
     * The hardest questions across the course's graded attempts, ranked by the share of
     * answers that were wrong. Only questions with a meaningful sample are shown.
     *
     * @return Collection<int, array{question: Question, wrongRate: float, responses: int}>
     */
    public function hardestQuestions(Course $course, int $limit = 5, int $minResponses = 3): Collection
    {
        $rows = DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->join('assessments', 'assessments.id', '=', 'attempts.assessment_id')
            ->where('assessments.course_id', $course->id)
            ->where('attempts.status', AttemptStatus::Graded->value)
            ->whereNotNull('attempt_answers.is_correct')
            ->groupBy('attempt_answers.question_id')
            ->havingRaw('count(*) >= ?', [$minResponses])
            ->select('attempt_answers.question_id')
            ->selectRaw('count(*) as responses')
            ->selectRaw('avg(case when attempt_answers.is_correct = 1 then 0.0 else 1.0 end) * 100 as wrong_rate')
            ->orderByDesc('wrong_rate')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $questions = Question::query()->whereIn('id', $rows->pluck('question_id'))->get()->keyBy('id');

        return $rows
            ->map(fn ($row) => [
                'question' => $questions->get($row->question_id),
                'wrongRate' => round((float) $row->wrong_rate, 1),
                'responses' => (int) $row->responses,
            ])
            ->filter(fn (array $r) => $r['question'] !== null)
            ->values();
    }

    /**
     * The pre → post knowledge gain: the mean score on pre-module diagnostics versus
     * post-module checks, and the difference. Reuses the assessment-stats aggregation so
     * it costs no extra query.
     *
     * @param  Collection<int, array{placement: AssessmentPlacement, average: ?float, attempts: int}>  $stats
     * @return array{pre: ?float, post: ?float, gain: ?float}
     */
    public function knowledgeGain(Collection $stats): array
    {
        $mean = function (AssessmentPlacement $placement) use ($stats): ?float {
            $relevant = $stats->filter(fn (array $s) => $s['placement'] === $placement && $s['average'] !== null && $s['attempts'] > 0);

            if ($relevant->isEmpty()) {
                return null;
            }

            return round((float) $relevant->avg('average'), 1);
        };

        $pre = $mean(AssessmentPlacement::PreModule);
        $post = $mean(AssessmentPlacement::PostModule);

        return [
            'pre' => $pre,
            'post' => $post,
            'gain' => ($pre !== null && $post !== null) ? round($post - $pre, 1) : null,
        ];
    }

    /**
     * The Section-6.5 class grade distribution: count of students per band on the
     * course's governing scale. Batched through GradebookService, so N+1-free.
     *
     * @return array{scale: ?\App\Models\GradeScale, bands: Collection<int, array{band: \App\Models\GradeBand, count: int}>, ungraded: int, total: int}
     */
    public function gradeDistribution(Course $course): array
    {
        $scale = $course->gradeScaleOrDefault();

        $students = $course->enrollments()
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->values();

        if ($scale === null || $students->isEmpty()) {
            return ['scale' => $scale, 'bands' => collect(), 'ungraded' => $students->count(), 'total' => $students->count()];
        }

        $scale->loadMissing('bands');
        $itemsByUser = $this->gradebook->itemsForMany($students, $course);

        $summaries = $students->map(fn ($student): GradebookSummary => $this->gradebook->summarize(
            $itemsByUser->get($student->id, collect()),
            $scale,
        ));

        $bands = $scale->bands->map(fn ($band) => [
            'band' => $band,
            'count' => $summaries->filter(fn (GradebookSummary $s) => $s->band?->is($band))->count(),
        ]);

        $ungraded = $summaries->filter(fn (GradebookSummary $s) => $s->band === null)->count();

        return [
            'scale' => $scale,
            'bands' => $bands,
            'ungraded' => $ungraded,
            'total' => $students->count(),
        ];
    }
}
