<?php

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Courses\CurriculumOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseCurriculumController extends Controller
{
    public function __construct(private readonly CurriculumOrderService $order) {}

    /**
     * The curriculum outline partial — re-fetched after each AJAX mutation so the
     * builder always reflects persisted state (the same server-renders-the-partial
     * pattern the admin data tables use).
     */
    public function show(Course $course): View
    {
        $this->authorize('view', $course);

        $course->load([
            'modules.lessons.media',
            'modules.assessments',
            'modules.assignments',
            'assessments',
            'assignments',
        ]);

        return view('courses.partials._curriculum', [
            'course' => $course,
            'canManage' => request()->user()->can('manageCurriculum', $course),
        ]);
    }

    /**
     * Persist a drag-and-drop (or keyboard) reorder of the whole outline in one call:
     * module order, and the single merged item order within each bucket — lessons,
     * assessments and assignments as equal siblings, moving freely between modules and
     * to/from the course-level bucket.
     *
     * Payload: order => [ { module_id: int|null, items: [ {type, id}, … ] }, … ]
     */
    public function reorder(Request $request, Course $course): JsonResponse
    {
        $this->authorize('manageCurriculum', $course);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*.module_id' => ['present', 'nullable', 'integer'],
            'order.*.items' => ['array'],
            'order.*.items.*.type' => ['required', 'string', Rule::in(CurriculumOrderService::TYPES)],
            'order.*.items.*.id' => ['required', 'integer'],
        ]);

        // Ownership of every referenced module/lesson/assessment/assignment is enforced
        // inside the service, which skips anything outside this course.
        $this->order->apply($course, $validated['order']);

        return response()->json(['ok' => true, 'message' => 'Order saved.']);
    }
}
