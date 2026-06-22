<?php

namespace App\Http\Controllers\Assessments;

use App\Enums\AttemptStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Services\Assessments\AttemptService;
use App\Services\Assessments\GradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The instructor grading queue: attempts handed in with essay / scenario-essay answers
 * awaiting a human. Each grade entry is rubric-free points + written feedback (rubrics
 * land in Section 6); once the last manual item is settled the attempt is finalised.
 */
class GradingController extends Controller
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly AttemptService $attempts,
    ) {}

    /**
     * Every submitted attempt awaiting grading on a course the instructor teaches.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Assessment::class);

        $user = $request->user();
        $isAdmin = $user->hasRole(Role::Admin->value) || $user->hasRole(Role::SuperAdmin->value);

        $queue = Attempt::query()
            ->where('status', AttemptStatus::Submitted->value)
            ->with(['assessment.course', 'user'])
            ->when(! $isAdmin, fn ($q) => $q->whereHas('assessment.course', fn ($c) => $c->forInstructor($user)))
            ->orderBy('submitted_at')
            ->paginate(20);

        return view('assessments.grading.index', ['attempts' => $queue]);
    }

    /**
     * The grade screen for one attempt: each pending manual answer with the student's
     * response, the points available and (for scenarios) the auto-scored objective hint.
     */
    public function show(Attempt $attempt): View
    {
        $this->authorize('grade', $attempt);

        $attempt->load(['assessment.course', 'user', 'answers.question']);

        $items = $attempt->answers
            ->filter(fn ($a) => $a->points_awarded === null && $a->question)
            ->map(function ($answer) use ($attempt) {
                $question = $answer->question;
                $layoutRow = $attempt->layoutFor($question->id) ?? [];
                $hint = $question->type->isScenario()
                    ? $this->grading->scenarioObjectiveSubtotal($question, $answer->response, $layoutRow)
                    : null;

                return [
                    'answer' => $answer,
                    'question' => $question,
                    'response' => $answer->response,
                    'max' => (float) ($layoutRow['points'] ?? $question->points),
                    'objective_hint' => $hint,
                ];
            })
            ->values();

        return view('assessments.grading.show', [
            'attempt' => $attempt,
            'items' => $items,
        ]);
    }

    /**
     * Save the manual grades + feedback, then finalise the attempt's score/pass-fail.
     */
    public function update(Request $request, Attempt $attempt): RedirectResponse
    {
        $this->authorize('grade', $attempt);

        $data = $request->validate([
            'grades' => ['required', 'array'],
            'grades.*.points' => ['required', 'numeric', 'min:0'],
            'grades.*.feedback' => ['nullable', 'string', 'max:20000'],
        ]);

        $answers = $attempt->answers()->whereNull('points_awarded')->get()->keyBy('id');

        foreach ($data['grades'] as $answerId => $grade) {
            $answer = $answers->get((int) $answerId);
            if (! $answer) {
                continue;
            }

            $max = (float) $answer->points_possible;
            $answer->forceFill([
                'points_awarded' => min((float) $grade['points'], $max),
                'feedback' => $grade['feedback'] ?? null,
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ])->save();
        }

        $this->attempts->finalizeAfterGrading($attempt);

        return redirect()
            ->route('grading.index')
            ->with('status', 'Attempt graded.');
    }
}
