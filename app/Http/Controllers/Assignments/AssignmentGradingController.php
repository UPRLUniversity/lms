<?php

namespace App\Http\Controllers\Assignments;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\GradeSubmissionRequest;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Services\Assignments\AssignmentGradingService;
use App\Services\Media\PrivateFileService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The assignment grading workspace: a queue filterable by course/assignment/status, and
 * a split view — the student's work beside the interactive rubric — with a
 * "Grade & next" flow and return-for-resubmission. Auditors browse it read-only.
 */
class AssignmentGradingController extends Controller
{
    public function __construct(
        private readonly AssignmentGradingService $grading,
        private readonly PrivateFileService $privateFiles,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Assignment::class);

        $user = $request->user();

        $status = $request->query('status', SubmissionStatus::Submitted->value);
        $validStatuses = array_map(fn (SubmissionStatus $s) => $s->value, SubmissionStatus::cases());

        $queue = Submission::query()
            ->with(['assignment.course', 'user', 'grade'])
            ->withCount('media')
            ->when(in_array($status, $validStatuses, true), fn ($q) => $q->where('status', $status))
            ->when($request->query('course'), fn ($q, $slug) => $q->whereHas('assignment.course', fn ($c) => $c->where('slug', $slug)))
            ->when($request->query('assignment'), fn ($q, $id) => $q->where('assignment_id', (int) $id))
            ->whereHas('assignment.course', fn ($c) => $this->visibleCourses($c, $user))
            ->orderBy('submitted_at')
            ->paginate(20)
            ->withQueryString();

        $courses = Course::query()
            ->whereHas('assignments')
            ->where(fn ($q) => $this->visibleCourses($q, $user))
            ->orderBy('title')
            ->get(['id', 'slug', 'title']);

        return view('assignments.grading.index', [
            'queue' => $queue,
            'courses' => $courses,
            'assignments' => $this->assignmentOptions($request, $courses),
            'status' => $status,
        ]);
    }

    /**
     * Split view: the submission (text + files with inline preview) beside the
     * interactive rubric + feedback editor.
     */
    public function show(Request $request, Submission $submission): View
    {
        $this->authorize('view', $submission);

        $submission->load([
            'assignment.course', 'assignment.rubric.criteria', 'user', 'grade', 'media', 'returnedBy',
        ]);

        return view('assignments.grading.show', [
            'submission' => $submission,
            'assignment' => $submission->assignment,
            'rubric' => $submission->assignment->rubric,
            'grade' => $submission->grade,
            'canGrade' => Gate::allows('grade', $submission),
            'files' => $submission->files()->map(fn ($media) => [
                'media' => $media,
                'previewUrl' => $this->previewUrl($media),
            ]),
            'versionCount' => Submission::where('assignment_id', $submission->assignment_id)
                ->where('user_id', $submission->user_id)
                ->count(),
            'queueCount' => $this->queueFor($request->user())->count(),
        ]);
    }

    /**
     * Save the grade (server recomputes all rubric math), then either return to the
     * queue or jump straight to the next waiting submission ("Grade & next").
     */
    public function update(GradeSubmissionRequest $request, Submission $submission): RedirectResponse
    {
        $this->grading->grade($submission, $request->user(), $request->validated());

        if ($request->boolean('and_next')) {
            $next = $this->queueFor($request->user())
                ->whereKeyNot($submission->id)
                ->first();

            if ($next) {
                return redirect()
                    ->route('grading.assignments.show', $next)
                    ->with('status', "Graded — here's the next one.");
            }
        }

        return redirect()
            ->route('grading.assignments.index')
            ->with('status', 'Submission graded.');
    }

    /**
     * Send the work back for another version — the note is required.
     */
    public function returnSubmission(Request $request, Submission $submission): RedirectResponse
    {
        $this->authorize('grade', $submission);

        $data = $request->validate(
            ['note' => ['required', 'string', 'max:5000']],
            ['note.required' => 'Add a note telling the student what to improve before returning their work.'],
        );

        $this->grading->returnForResubmission($submission, $request->user(), $data['note']);

        return redirect()
            ->route('grading.assignments.index')
            ->with('status', 'Returned for resubmission — the student has been asked for a new version.');
    }

    /**
     * Constrain a course query to what this user may grade/see: admins + auditors see
     * all, instructors their own courses.
     *
     * @param  Builder<Course>  $query
     */
    private function visibleCourses(Builder $query, $user): Builder
    {
        $seesAll = $user->hasRole(Role::Admin->value)
            || $user->hasRole(Role::SuperAdmin->value)
            || ($user->hasRole(Role::Auditor->value) && $user->can(Permission::AssignmentsView->value));

        if (! $seesAll) {
            $query->forInstructor($user);
        }

        return $query;
    }

    /**
     * The user's pending queue (oldest first) — powers "Grade & next".
     *
     * @return Builder<Submission>
     */
    private function queueFor($user): Builder
    {
        return Submission::query()
            ->where('status', SubmissionStatus::Submitted->value)
            ->whereHas('assignment.course', fn ($c) => $this->visibleCourses($c, $user))
            ->orderBy('submitted_at');
    }

    /**
     * Assignment filter options, narrowed to the selected course when one is chosen.
     */
    private function assignmentOptions(Request $request, $courses)
    {
        return Assignment::query()
            ->when($request->query('course'), function ($q, $slug) {
                $q->whereHas('course', fn ($c) => $c->where('slug', $slug));
            }, function ($q) use ($courses) {
                $q->whereIn('course_id', $courses->pluck('id'));
            })
            ->orderBy('title')
            ->get(['id', 'title', 'course_id']);
    }

    private function previewUrl($media): ?string
    {
        $inline = str_starts_with((string) $media->mime, 'image/') || $media->mime === 'application/pdf';

        return $inline ? $this->privateFiles->temporaryUrl($media) : null;
    }
}
