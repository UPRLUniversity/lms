<?php

namespace App\Http\Controllers\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LessonProgress;
use App\Services\Courses\LearningService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The instructor's per-course progress view: every enrolled student's percentage,
 * last activity and a per-lesson completion heat-strip. Admins and the read-only
 * auditor see it for any course; instructors only for the courses they teach
 * (the viewRoster gate). Mutates nothing.
 */
class CourseProgressController extends Controller
{
    public function __construct(private readonly LearningService $learning) {}

    public function index(Request $request, Course $course): View
    {
        $this->authorize('viewRoster', $course);

        $course->loadMissing([
            'modules' => fn ($q) => $q->orderBy('position'),
            'modules.lessons' => fn ($q) => $q->orderBy('position'),
        ]);

        // Ordered lesson sequence → the columns of the heat-strip.
        $lessons = $this->learning->sequence($course);

        // Enrolled learners (active + completed), with their cached percentage.
        $enrollments = $course->enrollments()
            ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value])
            ->with('user')
            ->get()
            ->sortBy(fn ($e) => $e->user->name)
            ->values();

        $studentIds = $enrollments->pluck('user_id');

        // Every relevant progress row in one query, grouped per student.
        $progressByStudent = $lessons->isEmpty() || $studentIds->isEmpty()
            ? collect()
            : LessonProgress::query()
                ->whereIn('user_id', $studentIds)
                ->whereIn('lesson_id', $lessons->pluck('id'))
                ->get()
                ->groupBy('user_id');

        $rows = $enrollments->map(function ($enrollment) use ($progressByStudent) {
            $rows = ($progressByStudent->get($enrollment->user_id) ?? collect());

            return [
                'enrollment' => $enrollment,
                'user' => $enrollment->user,
                'percent' => (int) $enrollment->progress_percent,
                'completedLessonIds' => $rows
                    ->where('status', LessonProgressStatus::Completed)
                    ->pluck('lesson_id')
                    ->flip(),
                'lastActivity' => $rows->max('updated_at'),
            ];
        });

        return view('courses.progress.index', [
            'course' => $course,
            'lessons' => $lessons,
            'rows' => $rows,
            'canManage' => $request->user()->can('manageRoster', $course),
        ]);
    }
}
