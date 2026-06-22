<?php

namespace App\Http\Controllers\Courses;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\LessonProgress;
use App\Services\Assessments\KnowledgeGainService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's learning history: one row per course they've engaged with — enrolled
 * date, progress, time spent, completion date and (Section 5) any pre/post knowledge-gain.
 */
class LearningHistoryController extends Controller
{
    public function index(Request $request, KnowledgeGainService $gainService): View
    {
        $user = $request->user();

        $enrollments = $user->enrollments()
            ->with(['course.department', 'course.modules'])
            ->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Completed->value,
                EnrollmentStatus::Withdrawn->value,
            ])
            ->orderByDesc('enrolled_at')
            ->get();

        // Pre/post knowledge-gain per course: every module with a graded pre+post pair.
        $gainsByCourse = $enrollments->mapWithKeys(function ($enrollment) use ($user, $gainService) {
            $gains = $enrollment->course->modules
                ->map(fn ($module) => $gainService->forStudentModule($user, $module))
                ->filter()
                ->values();

            return [$enrollment->course_id => $gains];
        });

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
            'gainsByCourse' => $gainsByCourse,
            'completedCount' => $enrollments->where('status', EnrollmentStatus::Completed)->count(),
        ]);
    }
}
