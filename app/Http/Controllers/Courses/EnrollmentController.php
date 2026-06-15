<?php

namespace App\Http\Controllers\Courses;

use App\Enums\EnrollmentStatus;
use App\Exceptions\EnrollmentException;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Courses\EnrollmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Student-facing enrolment: self-enrol from the catalogue (the service resolves the
 * outcome — active / pending / waitlisted) and self-withdraw from "My Learning".
 */
class EnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    /**
     * Self-enrol in a course. The button shown on the course page already reflects the
     * mode/capacity, but the service re-checks every rule so a stale page can't enrol
     * into an invite-only or closed course.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        try {
            $enrollment = $this->enrollments->selfEnroll($request->user(), $course);
        } catch (EnrollmentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('learning.index')
            ->with('status', $this->welcomeMessage($enrollment));
    }

    /**
     * Withdraw from a course (self-service). Authorized by the withdraw policy; the
     * service frees the seat and auto-promotes the next waitlisted student.
     */
    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('withdraw', $enrollment);

        $title = $enrollment->course->title;
        $this->enrollments->withdraw($enrollment);

        return back()->with('status', "You've withdrawn from “{$title}”.");
    }

    /**
     * The warm, status-aware confirmation copy for a fresh enrolment.
     */
    private function welcomeMessage(Enrollment $enrollment): string
    {
        return match ($enrollment->status) {
            EnrollmentStatus::Active => "You're in — start learning.",
            EnrollmentStatus::Pending => "Request received — we'll let you know once it's approved.",
            EnrollmentStatus::Waitlisted => "You're on the waitlist — position #{$enrollment->waitlistPosition()}.",
            default => 'Your enrolment has been updated.',
        };
    }
}
