<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\Reporting\CourseAnalyticsService;
use Illuminate\View\View;

/**
 * The instructor's per-course analytics drill-down (Section 10): progress distribution,
 * assessment performance, the hardest questions, pre → post knowledge gain and the
 * Section-6.5 class grade distribution. Authorized through the same gate as the
 * gradebook matrix, so admins and the read-only auditor may view any course.
 */
class InstructorAnalyticsController extends Controller
{
    public function __construct(private readonly CourseAnalyticsService $analytics) {}

    public function show(Course $course): View
    {
        $this->authorize('viewGradebookMatrix', $course);

        $assessmentStats = $this->analytics->assessmentStats($course);

        return view('reports.course-analytics', [
            'course' => $course,
            'progress' => $this->analytics->progressDistribution($course),
            'assessmentStats' => $assessmentStats,
            'hardest' => $this->analytics->hardestQuestions($course),
            'knowledgeGain' => $this->analytics->knowledgeGain($assessmentStats),
            'distribution' => $this->analytics->gradeDistribution($course),
        ]);
    }
}
