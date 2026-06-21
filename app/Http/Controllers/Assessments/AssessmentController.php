<?php

namespace App\Http\Controllers\Assessments;

use App\Enums\AssessmentPlacement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessments\UpdateAssessmentRequest;
use App\Models\Assessment;
use App\Models\Course;
use App\Services\Assessments\AssessmentBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Assessment lifecycle for instructors: create at a curriculum attachment point, edit in
 * the builder (settings + content), publish/unpublish (with validation), preview-as-student
 * and delete.
 */
class AssessmentController extends Controller
{
    public function __construct(private readonly AssessmentBuilderService $builder) {}

    /**
     * Create a draft assessment at an attachment point, then open its builder.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('manageCurriculum', $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'placement' => ['required', 'string', 'in:'.implode(',', AssessmentPlacement::values())],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'selection_mode' => ['nullable', 'string', 'in:fixed,pooled'],
        ]);

        // A pre/post placement must name a module that belongs to this course.
        if (AssessmentPlacement::from($data['placement'])->attachesToModule()) {
            $module = $course->modules()->find($data['module_id'] ?? 0);
            abort_unless($module !== null, 422, 'Choose a module for a pre/post assessment.');
        }

        $assessment = $this->builder->createAt($course, $data, $request->user());

        return redirect()
            ->route('assessments.edit', [$course, $assessment])
            ->with('status', 'Assessment created. Add questions to get started.');
    }

    /**
     * The builder: settings panel + content (fixed list or pool rules).
     */
    public function edit(Course $course, Assessment $assessment): View
    {
        $this->assertBelongs($course, $assessment);
        $this->authorize('manage', $assessment);

        $assessment->load(['questions.category', 'poolRules.category']);

        // The whole course bank, shaped for the fixed-mode picker (no correctness leaked —
        // this is an instructor-only page, but we only need display fields anyway).
        $bank = $course->questions()
            ->with('category')
            ->latest()
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'prompt' => \Illuminate\Support\Str::limit(strip_tags($q->prompt), 100),
                'type' => $q->type->shortLabel(),
                'difficulty' => $q->difficulty->value,
                'category_id' => $q->category_id,
                'category' => $q->category?->name,
                'points' => (float) $q->points,
            ])->values();

        return view('assessments.builder', [
            'course' => $course,
            'assessment' => $assessment,
            'bank' => $bank,
            'bankCategories' => $course->questionCategories()->orderBy('name')->get(),
            'publishErrors' => $this->builder->validateForPublish($assessment),
            'modules' => $course->modules()->orderBy('position')->get(),
        ]);
    }

    public function update(UpdateAssessmentRequest $request, Course $course, Assessment $assessment): RedirectResponse
    {
        $this->assertBelongs($course, $assessment);

        $this->builder->updateSettings($assessment, $request->validated());

        return redirect()
            ->route('assessments.edit', [$course, $assessment])
            ->with('status', 'Settings saved.');
    }

    public function publish(Course $course, Assessment $assessment): RedirectResponse
    {
        $this->assertBelongs($course, $assessment);
        $this->authorize('manage', $assessment);

        $errors = $this->builder->validateForPublish($assessment);
        if (! empty($errors)) {
            return back()->with('error', $errors[0]);
        }

        $this->builder->publish($assessment);

        return back()->with('status', 'Assessment published.');
    }

    public function unpublish(Course $course, Assessment $assessment): RedirectResponse
    {
        $this->assertBelongs($course, $assessment);
        $this->authorize('manage', $assessment);

        $this->builder->unpublish($assessment);

        return back()->with('status', 'Assessment unpublished — students can no longer start it.');
    }

    /**
     * Preview-as-student: render the take screen from a freshly-built (unsaved) layout, so
     * the instructor sees exactly what a learner sees — without recording an attempt.
     */
    public function preview(Course $course, Assessment $assessment): View
    {
        $this->assertBelongs($course, $assessment);
        $this->authorize('preview', $assessment);

        return view('assessments.preview', [
            'course' => $course,
            'assessment' => $assessment->load(['questions', 'poolRules.category']),
        ]);
    }

    public function destroy(Course $course, Assessment $assessment): RedirectResponse
    {
        $this->assertBelongs($course, $assessment);
        $this->authorize('manage', $assessment);

        $assessment->delete();

        return redirect()
            ->route('courses.curriculum', $course)
            ->with('status', 'Assessment deleted.');
    }

    private function assertBelongs(Course $course, Assessment $assessment): void
    {
        abort_unless($assessment->course_id === $course->id, 404);
    }
}
