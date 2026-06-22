<?php

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\Courses\LearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The learning player: the flagship learner experience. Renders a lesson with its
 * curriculum sidebar, advances the student through "Complete & Continue", persists
 * lightweight progress pings, and gates sequential courses server-side.
 *
 * All progress logic lives in LearningService; this controller only resolves the
 * lesson, authorizes, and shapes the response (HTML page or JSON for the async
 * progress writes).
 */
class LearnController extends Controller
{
    public function __construct(private readonly LearningService $learning) {}

    /**
     * "Continue learning" deep-link: resume at the first incomplete lesson (or the
     * start when revisiting a finished course). The single resume entry point used
     * everywhere — My Learning, the course page, the dashboard.
     */
    public function resume(Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $snapshot = $this->learning->snapshot(request()->user(), $course);
        $lesson = $snapshot->resumeLesson();

        if (! $lesson) {
            return redirect()
                ->route('learning.index')
                ->with('error', 'This course has no lessons yet.');
        }

        return redirect()->route('learn.show', [$course, $lesson]);
    }

    /**
     * The player page for a single lesson.
     */
    public function show(Request $request, Course $course, Lesson $lesson): View|RedirectResponse
    {
        $this->loadCurriculum($course);
        $lesson = $this->resolveLesson($course, $lesson);

        $this->authorize('learn', $lesson);

        $snapshot = $this->learning->snapshot($request->user(), $course);
        $outline = $this->learning->outline($request->user(), $course, $snapshot);

        // Sequential gate — a locked lesson is unreachable even by direct URL. The outline
        // is assessment-aware, so a lesson sitting behind an unpassed required assessment
        // locks too.
        if ($outline->isLessonLocked($lesson)) {
            return redirect()
                ->route('learn.show', [$course, $snapshot->resumeLesson()])
                ->with('error', 'Complete the previous step to unlock this one.');
        }

        $enrollment = $course->enrollmentFor($request->user());
        $canTrack = $request->user()->can('track', $lesson);

        // Opening a lesson counts as starting it (never downgrades a completed one).
        // Staff previews don't track, so they leave no progress trail.
        if ($canTrack) {
            $this->learning->recordPosition($request->user(), $lesson);
        }

        return view('learn.show', [
            'course' => $course,
            'lesson' => $lesson,
            'snapshot' => $snapshot,
            'outline' => $outline,
            'previous' => $snapshot->previous($lesson),
            'next' => $snapshot->next($lesson),
            'isPreview' => $enrollment === null,
            'canTrack' => $canTrack,
        ]);
    }

    /**
     * Mark a lesson complete and advance. Async (JSON) for the in-page player; falls
     * back to a redirect to the next lesson when posted as a plain form.
     */
    public function complete(Request $request, Course $course, Lesson $lesson): JsonResponse|RedirectResponse
    {
        $lesson = $this->resolveLesson($course, $lesson);
        $this->authorize('track', $lesson);

        $result = $this->learning->markComplete($request->user(), $lesson);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'percent' => $result['percent'],
                'module_completed' => $result['module_completed'],
                'course_completed' => $result['course_completed'],
                'module_title' => $lesson->module?->title,
                'next_url' => $result['next'] ? route('learn.show', [$course, $result['next']]) : null,
                'congratulations_url' => $result['course_completed'] ? route('learn.congratulations', $course) : null,
            ]);
        }

        if ($result['course_completed']) {
            return redirect()->route('learn.congratulations', $course);
        }

        if ($result['next']) {
            return redirect()->route('learn.show', [$course, $result['next']]);
        }

        return redirect()
            ->route('learn.show', [$course, $lesson])
            ->with('status', 'Lesson complete.');
    }

    /**
     * Un-mark a completed lesson.
     */
    public function incomplete(Request $request, Course $course, Lesson $lesson): JsonResponse|RedirectResponse
    {
        $lesson = $this->resolveLesson($course, $lesson);
        $this->authorize('track', $lesson);

        $result = $this->learning->markIncomplete($request->user(), $lesson);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'percent' => $result['percent']]);
        }

        return back()->with('status', 'Marked as incomplete.');
    }

    /**
     * Lightweight progress ping — last video position + seconds spent. Returns 204;
     * resilient to frequent calls (idempotent, monotonic in the service).
     */
    public function position(Request $request, Course $course, Lesson $lesson): Response
    {
        $lesson = $this->resolveLesson($course, $lesson);
        $this->authorize('track', $lesson);

        $data = $request->validate([
            'position_seconds' => ['nullable', 'integer', 'min:0', 'max:360000'],
            'seconds_spent' => ['nullable', 'integer', 'min:0', 'max:360000'],
        ]);

        $this->learning->recordPosition(
            $request->user(),
            $lesson,
            $data['position_seconds'] ?? null,
            $data['seconds_spent'] ?? null,
        );

        return response()->noContent();
    }

    /**
     * The full-screen congratulations page shown once a course hits 100%.
     */
    public function congratulations(Request $request, Course $course): View|RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $this->loadCurriculum($course);
        $snapshot = $this->learning->snapshot($request->user(), $course);

        // Not finished yet → send them back to where they left off.
        if (! $snapshot->isCourseComplete()) {
            return redirect()->route('learn.resume', $course);
        }

        $enrollment = $course->enrollmentFor($request->user());

        return view('learn.congratulations', [
            'course' => $course,
            'snapshot' => $snapshot,
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Eager-load the curriculum + media once, ordered, so the sidebar and snapshot
     * never N+1.
     */
    private function loadCurriculum(Course $course): void
    {
        $course->loadMissing([
            'modules' => fn ($q) => $q->orderBy('position'),
            'modules.lessons' => fn ($q) => $q->orderBy('position'),
            'modules.lessons.media',
        ]);
    }

    /**
     * Ensure the lesson belongs to this course (a mismatched pair is a 404), and hand
     * back the instance hydrated with its module/course so policies can resolve it.
     */
    private function resolveLesson(Course $course, Lesson $lesson): Lesson
    {
        $lesson->loadMissing('module.course');

        abort_unless($lesson->module && $lesson->module->course_id === $course->id, 404);

        return $lesson;
    }

    /**
     * Course-level access for the resume/congratulations endpoints (no specific
     * lesson): enrolled learner or staff/auditor preview.
     */
    private function authorizeCourseAccess(Course $course): void
    {
        $user = request()->user();
        $enrollment = $course->enrollmentFor($user);

        if ($enrollment && $enrollment->grantsLearningAccess()) {
            return;
        }

        $this->authorize('view', $course);
    }
}
