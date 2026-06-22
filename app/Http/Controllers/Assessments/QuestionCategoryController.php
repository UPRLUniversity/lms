<?php

namespace App\Http\Controllers\Assessments;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuestionCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Course-scoped question categories, managed inline from the bank (AJAX).
 */
class QuestionCategoryController extends Controller
{
    public function store(Request $request, Course $course): JsonResponse
    {
        $this->authorize('manageCurriculum', $course);

        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        $category = $course->questionCategories()->create([
            'name' => $data['name'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Category added.',
            'category' => ['id' => $category->id, 'name' => $category->name],
        ]);
    }

    public function update(Request $request, Course $course, QuestionCategory $category): JsonResponse
    {
        $this->authorize('manageCurriculum', $course);
        abort_unless($category->course_id === $course->id, 404);

        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $category->update(['name' => $data['name']]);

        return response()->json(['ok' => true, 'message' => 'Category renamed.']);
    }

    public function destroy(Course $course, QuestionCategory $category): JsonResponse
    {
        $this->authorize('manageCurriculum', $course);
        abort_unless($category->course_id === $course->id, 404);

        // Questions keep their bank entry; their category simply clears (nullOnDelete).
        $category->delete();

        return response()->json(['ok' => true, 'message' => 'Category removed.']);
    }
}
