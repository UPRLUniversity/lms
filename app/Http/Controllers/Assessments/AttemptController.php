<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Course;
use App\Services\Assessments\AttemptPresenter;
use App\Services\Assessments\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student taking engine, inside the player frame: the start screen, the
 * one-question-per-screen runner with its frozen layout + server-authoritative timer,
 * immediate autosave, flag-for-review and submission.
 *
 * Every write is authorised against the attempt's owner and validated against the frozen
 * layout in AttemptService — the controller only shapes requests and responses.
 */
class AttemptController extends Controller
{
    public function __construct(
        private readonly AttemptService $attempts,
        private readonly AttemptPresenter $presenter,
    ) {}

    /**
     * The start screen: instructions, counts, timing, attempts used/left, passing score,
     * and either "Start attempt" or "Resume" (when one is already in progress).
     */
    public function start(Request $request, Course $course, Assessment $assessment): View|RedirectResponse
    {
        $this->assertBelongs($course, $assessment);
        $this->authorize('take', $assessment);

        $user = $request->user();
        $inProgress = $assessment->inProgressAttemptFor($user);

        // Auto-submit a stale in-progress attempt before showing the screen.
        if ($inProgress) {
            $inProgress = $this->attempts->ensureFresh($inProgress);
            if (! $inProgress->isInProgress()) {
                return redirect()->route('attempts.result', $inProgress);
            }
        }

        $course->loadMissing(['modules' => fn ($q) => $q->orderBy('position')]);

        return view('learn.assessment.start', [
            'course' => $course,
            'assessment' => $assessment,
            'inProgress' => $inProgress,
            'attemptsUsed' => $assessment->attemptsUsedBy($user),
            'attemptsLeft' => $assessment->attemptsLeftFor($user),
            'history' => $assessment->attemptsFor($user)->where('status', '!=', 'in_progress'),
            'canStart' => $assessment->canStart($user),
        ]);
    }

    /**
     * Begin a new attempt (or resume the in-progress one). Server-side enforcement of the
     * window + attempt cap lives in AttemptService::startAttempt.
     */
    public function store(Request $request, Course $course, Assessment $assessment): RedirectResponse
    {
        $this->assertBelongs($course, $assessment);
        $this->authorize('take', $assessment);

        $user = $request->user();

        if ($existing = $assessment->inProgressAttemptFor($user)) {
            return redirect()->route('attempts.show', $existing);
        }

        try {
            $attempt = $this->attempts->startAttempt($assessment, $user);
        } catch (\DomainException $e) {
            return redirect()
                ->route('assessments.start', [$course, $assessment])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('attempts.show', $attempt);
    }

    /**
     * The take screen — one question per screen, progress map, flagging, timer. Auto-submits
     * a timed-out attempt on load, then sends the student to the result.
     */
    public function show(Request $request, Attempt $attempt): View|RedirectResponse
    {
        $this->authorize('view', $attempt);

        $attempt = $this->attempts->ensureFresh($attempt);

        if (! $attempt->isInProgress()) {
            return redirect()->route('attempts.result', $attempt);
        }

        // Only the owner takes the attempt (a grader viewing is sent to the result view).
        abort_unless($attempt->user_id === $request->user()->id, 403);

        $attempt->loadMissing('assessment.course');

        return view('learn.assessment.take', [
            'course' => $attempt->assessment->course,
            'assessment' => $attempt->assessment,
            'attempt' => $attempt,
            'items' => $this->presenter->takeItems($attempt),
            'remainingSeconds' => $attempt->remainingSeconds(),
        ]);
    }

    /**
     * Autosave one answer (and/or its flag). Validated against the frozen layout in the
     * service; a tamper attempt (question not in this attempt) is rejected.
     */
    public function answer(Request $request, Attempt $attempt): JsonResponse
    {
        $this->authorize('continue', $attempt);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'flagged' => ['nullable', 'boolean'],
        ]);

        try {
            $this->attempts->saveAnswer(
                $attempt,
                $data['question_id'],
                $request->input('response'),
                $request->has('flagged') ? $request->boolean('flagged') : null,
            );
        } catch (\DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function submit(Request $request, Attempt $attempt): JsonResponse|RedirectResponse
    {
        $this->authorize('continue', $attempt);

        $this->attempts->submit($attempt);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'result_url' => route('attempts.result', $attempt)]);
        }

        return redirect()->route('attempts.result', $attempt);
    }

    private function assertBelongs(Course $course, Assessment $assessment): void
    {
        abort_unless($assessment->course_id === $course->id, 404);
    }
}
