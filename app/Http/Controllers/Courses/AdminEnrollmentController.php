<?php

namespace App\Http\Controllers\Courses;

use App\Exceptions\EnrollmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\AdminEnrollRequest;
use App\Models\Course;
use App\Models\User;
use App\Services\Courses\EnrollmentService;
use Illuminate\Http\RedirectResponse;

/**
 * Staff enrolling a user directly into a course (status active). The single endpoint
 * behind both entry points — the course roster ("add a student") and a user's admin
 * page ("enrol in a course") — each of which posts the matching course_id + user_id.
 */
class AdminEnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function store(AdminEnrollRequest $request): RedirectResponse
    {
        $student = User::findOrFail($request->integer('user_id'));
        $course = Course::findOrFail($request->integer('course_id'));

        try {
            $this->enrollments->adminEnroll($student, $course, $request->user());
        } catch (EnrollmentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "{$student->name} was enrolled in “{$course->title}”.");
    }
}
