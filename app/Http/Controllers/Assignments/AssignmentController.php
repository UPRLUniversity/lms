<?php

namespace App\Http\Controllers\Assignments;

use App\Enums\MediaPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assignments\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Media;
use App\Models\Rubric;
use App\Services\Assignments\AssignmentBuilderService;
use App\Services\Media\PrivateFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Assignment lifecycle for instructors: create at a curriculum attachment point, edit in
 * the builder (settings + rubric + resources), publish/unpublish (gradebook-safety gate)
 * and delete. Auditors reach the builder read-only through the view ability.
 */
class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentBuilderService $builder,
        private readonly PrivateFileService $privateFiles,
    ) {}

    /**
     * Create a draft assignment (optionally attached to a module), then open its builder.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('manageCurriculum', $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'module_id' => ['nullable', 'integer'],
        ]);

        if (! empty($data['module_id'])) {
            abort_unless($course->modules()->whereKey($data['module_id'])->exists(), 422,
                'Choose a module on this course.');
        }

        $assignment = $this->builder->createAt($course, $data, $request->user());

        return redirect()
            ->route('assignments.edit', [$course, $assignment])
            ->with('status', 'Assignment created. Write the brief to get started.');
    }

    /**
     * The builder: settings, rubric attachment, resources and the publish panel.
     * Managers edit; auditors get the same page read-only.
     */
    public function edit(Request $request, Course $course, Assignment $assignment): View
    {
        $this->assertBelongs($course, $assignment);
        $this->authorize('view', $assignment);

        $assignment->load(['rubric.criteria', 'module']);

        return view('assignments.builder', [
            'course' => $course,
            'assignment' => $assignment,
            'canManage' => Gate::allows('manage', $assignment),
            'rubrics' => Rubric::query()
                ->where(fn ($q) => $q
                    ->where('created_by', $assignment->created_by)
                    ->orWhere('created_by', $request->user()->id))
                ->withCount('criteria')
                ->orderBy('name')
                ->get(),
            'modules' => $course->modules()->orderBy('position')->get(),
            'resources' => $assignment->resources(),
            'publishErrors' => $this->builder->validateForPublish($assignment),
            'submissionCount' => $assignment->submissions()->count(),
        ]);
    }

    public function update(UpdateAssignmentRequest $request, Course $course, Assignment $assignment): RedirectResponse
    {
        $this->assertBelongs($course, $assignment);

        $this->builder->updateSettings($assignment, $request->validated());

        return redirect()
            ->route('assignments.edit', [$course, $assignment])
            ->with('status', 'Assignment saved.');
    }

    public function publish(Course $course, Assignment $assignment): RedirectResponse
    {
        $this->assertBelongs($course, $assignment);
        $this->authorize('manage', $assignment);

        $errors = $this->builder->validateForPublish($assignment);
        if (! empty($errors)) {
            return back()->with('error', $errors[0]);
        }

        $this->builder->publish($assignment);

        return back()->with('status', 'Assignment published — it now appears in the course curriculum.');
    }

    public function unpublish(Course $course, Assignment $assignment): RedirectResponse
    {
        $this->assertBelongs($course, $assignment);
        $this->authorize('manage', $assignment);

        $this->builder->unpublish($assignment);

        return back()->with('status', 'Assignment unpublished — students can no longer open it.');
    }

    public function destroy(Course $course, Assignment $assignment): RedirectResponse
    {
        $this->assertBelongs($course, $assignment);
        $this->authorize('manage', $assignment);

        $assignment->delete();

        return redirect()
            ->route('courses.edit', $course)
            ->with('status', 'Assignment deleted.');
    }

    /**
     * Attach an instructor resource (brief/template) to the assignment.
     */
    public function storeResource(Request $request, Course $course, Assignment $assignment): RedirectResponse
    {
        $this->assertBelongs($course, $assignment);
        $this->authorize('manage', $assignment);

        $purpose = MediaPurpose::AssignmentResources;
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', $purpose->allowedMimes()),
                'max:'.$purpose->maxKb(),
            ],
        ]);

        $this->privateFiles->store($request->file('file'), $purpose, $assignment);

        return back()->with('status', 'Resource attached.');
    }

    public function destroyResource(Course $course, Assignment $assignment, Media $media): RedirectResponse
    {
        $this->assertBelongs($course, $assignment);
        $this->authorize('manage', $assignment);
        abort_unless($media->mediable_type === $assignment->getMorphClass()
            && (int) $media->mediable_id === $assignment->id, 404);

        $this->privateFiles->delete($media);

        return back()->with('status', 'Resource removed.');
    }

    private function assertBelongs(Course $course, Assignment $assignment): void
    {
        abort_unless($assignment->course_id === $course->id, 404);
    }
}
