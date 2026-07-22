<?php

namespace App\Http\Controllers\Assignments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\StoreSubmissionRequest;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Services\Assignments\SubmissionService;
use App\Services\Media\PrivateFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student side of an assignment, inside the player frame: the brief (instructions,
 * resources, due countdown), handing in text/files, the immutable version history and
 * the graded result with its rubric breakdown.
 */
class SubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionService $submissions,
        private readonly PrivateFileService $privateFiles,
    ) {}

    /**
     * The assignment page: brief + submit form + version history + result.
     */
    public function show(Request $request, Course $course, Assignment $assignment): View
    {
        $this->assertBelongs($course, $assignment);
        $this->authorize('submit', $assignment);

        $user = $request->user();

        $versions = $assignment->submissionsFor($user)->load(['grade', 'media']);
        $latest = $versions->first();

        return view('learn.assignment.show', [
            'course' => $course,
            'assignment' => $assignment->load('rubric.criteria'),
            'resources' => $assignment->resources(),
            'versions' => $versions,
            'latest' => $latest,
            'grade' => $latest?->isGraded() ? $latest->grade : null,
        ]);
    }

    /**
     * Hand in a new version. Late policy + type rules + versioning live in the service.
     */
    public function store(StoreSubmissionRequest $request, Course $course, Assignment $assignment): RedirectResponse|JsonResponse
    {
        $this->assertBelongs($course, $assignment);

        $submission = $this->submissions->submit(
            $assignment,
            $request->user(),
            $request->validated('body'),
            $request->file('files', []),
        );

        $message = $submission->is_late
            ? "Version {$submission->version} submitted — after the due date, so it's marked late."
            : "Version {$submission->version} submitted. Nice work!";

        // The upload-progress form posts via XHR and expects JSON; plain form posts redirect.
        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('assignments.show', [$course, $assignment]),
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('assignments.show', [$course, $assignment])
            ->with('status', $message);
    }

    /**
     * One immutable version, viewable forever: body, files, grade/feedback if that
     * version was graded, and the return note if it was sent back.
     */
    public function showVersion(Request $request, Submission $submission): View
    {
        $this->authorize('view', $submission);

        $submission->load(['assignment.course', 'assignment.rubric.criteria', 'grade.grader', 'user', 'media', 'returnedBy']);

        return view('learn.assignment.version', [
            'submission' => $submission,
            'assignment' => $submission->assignment,
            'course' => $submission->assignment->course,
            'files' => $submission->files()->map(fn ($media) => [
                'media' => $media,
                'previewUrl' => $this->previewUrl($media),
            ]),
        ]);
    }

    /**
     * A short-lived signed URL for inline preview of pdf/images; null for other types
     * (they get the download route).
     */
    private function previewUrl($media): ?string
    {
        $inline = str_starts_with((string) $media->mime, 'image/') || $media->mime === 'application/pdf';

        return $inline ? $this->privateFiles->temporaryUrl($media) : null;
    }

    private function assertBelongs(Course $course, Assignment $assignment): void
    {
        abort_unless($assignment->course_id === $course->id, 404);
    }
}
