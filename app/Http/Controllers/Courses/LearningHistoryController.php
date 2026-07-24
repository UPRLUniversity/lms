<?php

namespace App\Http\Controllers\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CourseGradeRecord;
use App\Models\LessonProgress;
use App\Models\Submission;
use App\Services\Assessments\KnowledgeGainService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's learning history: one row per course they've engaged with — enrolled
 * date, progress, time spent, completion date, any pre/post knowledge-gain (Section 5)
 * and graded assignment scores (Section 6).
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

        // Graded assignment scores per course: the latest graded version per assignment,
        // shaped for the Assignments column (one query, no per-row lookups).
        $assignmentsByCourse = Submission::query()
            ->where('user_id', $user->id)
            ->where('status', SubmissionStatus::Graded->value)
            ->with(['grade', 'assignment:id,course_id,title,max_points'])
            ->orderBy('version')
            ->get()
            ->groupBy('assignment_id')
            ->map(fn ($versions) => $versions->last()) // highest graded version wins
            ->filter(fn (Submission $s) => $s->assignment !== null && $s->grade !== null)
            ->groupBy(fn (Submission $s) => $s->assignment->course_id);

        // Section 6.5: the recorded final grade per course, if the completion snapshot
        // has been written (one query, no per-row lookups).
        $gradeByCourse = CourseGradeRecord::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $enrollments->pluck('course_id'))
            ->current()
            ->get()
            ->keyBy('course_id');

        // Section 7: the issued certificate per course, if any (one query, no per-row lookups).
        $certificateByCourse = Certificate::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $enrollments->pluck('course_id'))
            ->get()
            ->keyBy('course_id');

        return view('learn.history', [
            'enrollments' => $enrollments,
            'secondsByCourse' => $secondsByCourse,
            'gainsByCourse' => $gainsByCourse,
            'assignmentsByCourse' => $assignmentsByCourse,
            'gradeByCourse' => $gradeByCourse,
            'certificateByCourse' => $certificateByCourse,
            'completedCount' => $enrollments->where('status', EnrollmentStatus::Completed)->count(),
        ]);
    }
}
