<?php

namespace App\Http\Controllers\Courses;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's "My Learning" page: their enrolled courses as status-aware cards
 * (Continue / Pending approval / Waitlisted #N), newest activity first.
 */
class MyLearningController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // The "live" enrolments worth showing — past (rejected/withdrawn) are hidden.
        $enrollments = $user->enrollments()
            ->with(['course.department', 'course.media', 'course.instructors'])
            ->whereIn('status', [
                EnrollmentStatus::Active->value,
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Waitlisted->value,
                EnrollmentStatus::Completed->value,
            ])
            ->orderByRaw($this->statusOrdering())
            ->orderByDesc('enrolled_at')
            ->get();

        return view('learning.index', [
            'enrollments' => $enrollments,
            'activeCount' => $enrollments->where('status', EnrollmentStatus::Active)->count(),
        ]);
    }

    /**
     * Sort active first, then pending, waitlisted, completed — so the cards a student
     * can act on lead the page. Expressed as a CASE so it works on every driver.
     */
    private function statusOrdering(): string
    {
        $order = [
            EnrollmentStatus::Active->value => 0,
            EnrollmentStatus::Pending->value => 1,
            EnrollmentStatus::Waitlisted->value => 2,
            EnrollmentStatus::Completed->value => 3,
        ];

        $cases = collect($order)
            ->map(fn ($weight, $status) => "WHEN '{$status}' THEN {$weight}")
            ->implode(' ');

        return "CASE status {$cases} ELSE 9 END";
    }
}
