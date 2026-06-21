<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Assessments\KnowledgeGainService;
use Illuminate\View\View;

/**
 * Class-level pre/post knowledge-gain per module — the instructor's view of the
 * beat-Teachable insight: for each module with both a pre and a post assessment, the
 * average lift across students who attempted both.
 */
class AssessmentInsightController extends Controller
{
    public function __construct(private readonly KnowledgeGainService $gain) {}

    public function index(Course $course, KnowledgeGainService $gain): View
    {
        $this->authorize('view', $course);

        $course->load(['modules' => fn ($q) => $q->orderBy('position')]);

        $modules = $course->modules->map(fn ($module) => [
            'module' => $module,
            'gain' => $this->gain->classAverageForModule($module),
        ])->filter(fn ($row) => $row['gain'] !== null)->values();

        return view('assessments.insights', [
            'course' => $course,
            'modules' => $modules,
        ]);
    }
}
