<?php

namespace App\Http\Controllers\Courses;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's learning history: one row per course they've engaged with — enrolled
 * date, progress, time spent and completion date. Assessment & certificate columns
 * are filled in by Sections 5 and 7.
 */
class LearningHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $enrollments = $user->enrollments()
            ->with(['course.department'])
            ->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Completed->value,
                EnrollmentStatus::Withdrawn->value,
            ])
            ->orderByDesc('enrolled_at')
            ->get();

        // Total engaged seconds per course in one grouped query (no per-row lookups).
        $secondsByCourse = LessonProgress::query()
            ->where('lesson_progress.user_id', $user->id)
            ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->groupBy('modules.course_id')
            ->selectRaw('modules.course_id as course_id, SUM(lesson_progress.seconds_spent) as seconds')
            ->pluck('seconds', 'course_id');

        return view('learn.history', [
            'enrollments' => $enrollments,
            'secondsByCourse' => $secondsByCourse,
            'completedCount' => $enrollments->where('status', EnrollmentStatus::Completed)->count(),
        ]);
    }
}
