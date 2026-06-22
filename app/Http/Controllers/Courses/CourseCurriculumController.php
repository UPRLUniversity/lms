<?php

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourseCurriculumController extends Controller
{
    /**
     * The curriculum outline partial — re-fetched after each AJAX mutation so the
     * builder always reflects persisted state (the same server-renders-the-partial
     * pattern the admin data tables use).
     */
    public function show(Course $course): View
    {
        $this->authorize('view', $course);

        $course->load(['modules.lessons.media', 'modules.assessments']);

        return view('courses.partials._curriculum', [
            'course' => $course,
            'canManage' => request()->user()->can('manageCurriculum', $course),
        ]);
    }

    /**
     * Persist a drag-and-drop reorder of the whole outline in one call: module
     * order, lesson order within each module, and lessons moved between modules.
     *
     * Payload: order => [ { module_id, lessons: [lessonId, …] }, … ]
     */
    public function reorder(Request $request, Course $course): JsonResponse
    {
        $this->authorize('manageCurriculum', $course);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*.module_id' => ['required', 'integer'],
            'order.*.lessons' => ['array'],
            'order.*.lessons.*' => ['integer'],
        ]);

        // Guard: every referenced module/lesson must belong to this course, so a
        // crafted payload can't re-home another course's content.
        $moduleIds = $course->modules()->pluck('id')->all();
        $lessonIds = Lesson::whereIn('module_id', $moduleIds)->pluck('id')->all();

        DB::transaction(function () use ($validated, $moduleIds, $lessonIds) {
            foreach ($validated['order'] as $moduleIndex => $row) {
                $moduleId = $row['module_id'];
                if (! in_array($moduleId, $moduleIds, true)) {
                    continue;
                }

                Module::whereKey($moduleId)->update(['position' => $moduleIndex + 1]);

                foreach (($row['lessons'] ?? []) as $lessonIndex => $lessonId) {
                    if (! in_array($lessonId, $lessonIds, true)) {
                        continue;
                    }

                    Lesson::whereKey($lessonId)->update([
                        'module_id' => $moduleId,
                        'position' => $lessonIndex + 1,
                    ]);
                }
            }
        });

        return response()->json(['ok' => true, 'message' => 'Order saved.']);
    }
}
