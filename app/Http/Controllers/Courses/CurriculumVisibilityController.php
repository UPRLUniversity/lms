<?php

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Hiding and restoring curriculum items — the safe alternative to deleting something
 * students have already worked on (Section 16).
 *
 * One polymorphic endpoint rather than a hide/restore pair on each of the four
 * controllers: the behaviour is identical for every type, so the only thing that varies
 * is how the item is looked up and confirmed to belong to the course.
 */
class CurriculumVisibilityController extends Controller
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const TYPES = [
        'module' => Module::class,
        'lesson' => Lesson::class,
        'assessment' => Assessment::class,
        'assignment' => Assignment::class,
    ];

    public function update(Request $request, Course $course, string $type, int $id): JsonResponse
    {
        $this->authorize('manageCurriculum', $course);

        $hidden = (bool) $request->validate([
            'hidden' => ['required', 'boolean'],
        ])['hidden'];

        $item = $this->resolve($course, $type, $id);

        $hidden ? $item->hide() : $item->restore();

        return response()->json([
            'ok' => true,
            'hidden' => $item->isHidden(),
            'message' => $hidden
                ? ucfirst($type).' hidden from students. Their work on it is kept.'
                : ucfirst($type).' is visible to students again.',
        ]);
    }

    /**
     * The item, proven to live in this course. Scoping the lookup by the course (rather
     * than finding then comparing) means a crafted id for someone else's course 404s
     * before anything is touched.
     */
    private function resolve(Course $course, string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        /** @var class-string<Model> $model */
        $model = self::TYPES[$type];

        $query = $model::query();

        if ($type === 'lesson') {
            $query->whereHas('module', fn ($q) => $q->where('course_id', $course->id));
        } else {
            $query->where('course_id', $course->id);
        }

        return $query->findOrFail($id);
    }
}
