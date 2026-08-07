<?php

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseChange;
use Illuminate\View\View;

/**
 * "What's changed" — the two read surfaces over the course change log (Section 16).
 *
 * Same rows, two audiences: a learner sees only material changes made since they enrolled
 * (edits predating them are just how the course has always been), while staff see the
 * complete history including cosmetic edits.
 */
class CourseChangeController extends Controller
{
    /**
     * Learner view, reached from the player sidebar and from the notification.
     */
    public function index(Course $course): View
    {
        // Same gate as announcements: enrolled, or staff previewing the course.
        $this->authorize('viewAnnouncements', $course);

        $enrolledAt = $course->enrollmentFor(request()->user())?->enrolled_at;

        return view('learn.changes', [
            'course' => $course,
            'changes' => CourseChange::query()
                ->where('course_id', $course->id)
                ->material()
                ->since($enrolledAt)
                ->with('author')
                ->latest('created_at')
                ->latest('id')
                ->paginate(25),
            'enrolledAt' => $enrolledAt,
        ]);
    }

    /**
     * Staff view — the full record, cosmetic edits included, for the builder.
     */
    public function history(Course $course): View
    {
        $this->authorize('update', $course);

        return view('courses.changes', [
            'course' => $course,
            'changes' => CourseChange::query()
                ->where('course_id', $course->id)
                ->with(['author', 'subject'])
                ->latest('created_at')
                ->latest('id')
                ->paginate(50),
        ]);
    }
}
