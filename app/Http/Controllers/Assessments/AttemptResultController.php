<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Course;
use App\Services\Assessments\AttemptPresenter;
use App\Services\Assessments\KnowledgeGainService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The result screen (score ring + policy-gated per-question review + pre/post gain card)
 * and the per-assessment attempt history.
 */
class AttemptResultController extends Controller
{
    public function __construct(
        private readonly AttemptPresenter $presenter,
        private readonly KnowledgeGainService $gain,
    ) {}

    public function show(Request $request, Attempt $attempt): View
    {
        $this->authorize('view', $attempt);

        $attempt->loadMissing(['assessment.course', 'assessment.module']);

        return view('learn.assessment.result', [
            'course' => $attempt->assessment->course,
            'assessment' => $attempt->assessment,
            'attempt' => $attempt,
            'canReview' => $this->presenter->canReview($attempt),
            'reviewItems' => $this->presenter->reviewItems($attempt),
            // Pre/post knowledge-gain card (only resolves for a graded post-module attempt
            // whose module also has a graded pre attempt).
            'gain' => $this->gain->forStudentAttempt($attempt),
        ]);
    }

    /**
     * Every attempt this student has made on the assessment.
     */
    public function history(Request $request, Course $course, Assessment $assessment): View
    {
        $this->assertBelongs($course, $assessment);
        $this->authorize('take', $assessment);

        return view('learn.assessment.history', [
            'course' => $course,
            'assessment' => $assessment,
            'attempts' => $assessment->attemptsFor($request->user()),
        ]);
    }

    private function assertBelongs(Course $course, Assessment $assessment): void
    {
        abort_unless($assessment->course_id === $course->id, 404);
    }
}
