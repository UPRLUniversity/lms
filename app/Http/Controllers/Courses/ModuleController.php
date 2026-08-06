<?php

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\StoreModuleRequest;
use App\Http\Requests\Courses\UpdateModuleRequest;
use App\Models\Course;
use App\Models\Module;
use App\Services\Courses\CurriculumImpactService;
use Illuminate\Http\JsonResponse;

/**
 * Curriculum modules — created, renamed and removed inline in the builder via AJAX.
 * Every action re-checks the manageCurriculum ability through the FormRequest /
 * route binding so an instructor can only ever touch their own course.
 */
class ModuleController extends Controller
{
    public function store(StoreModuleRequest $request, Course $course): JsonResponse
    {
        $module = $course->modules()->create([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'position' => (int) $course->modules()->max('position') + 1,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Module added.',
            'module_id' => $module->id,
        ]);
    }

    public function update(UpdateModuleRequest $request, Course $course, Module $module): JsonResponse
    {
        abort_unless($module->course_id === $course->id, 404);

        $module->update([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
        ]);

        return response()->json(['ok' => true, 'message' => 'Module updated.']);
    }

    /**
     * Removing a module takes its lessons, assessments and assignments with it, so the
     * impact guard looks at everything beneath it before anything is destroyed. A module
     * any student has worked through is refused and offered as a hide instead.
     */
    public function destroy(Course $course, Module $module, CurriculumImpactService $impact): JsonResponse
    {
        $this->authorize('manageCurriculum', $course);
        abort_unless($module->course_id === $course->id, 404);

        try {
            $impact->assertDeletable($module);
        } catch (\DomainException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'can_hide' => true,
            ], 422);
        }

        $module->delete();

        return response()->json(['ok' => true, 'message' => 'Module removed.']);
    }
}
