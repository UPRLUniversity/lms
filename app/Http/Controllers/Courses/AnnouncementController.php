<?php

namespace App\Http\Controllers\Courses;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Models\User;
use App\Notifications\CourseAnnouncementNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * Instructor-composed course announcements: posted from the builder, notified to
 * every actively-enrolled (or completed) student per their preferences, and listed
 * on both the builder's Announcements tab (the author's view) and the learner-facing
 * page linked from the player sidebar.
 */
class AnnouncementController extends Controller
{
    /**
     * Learner-facing list — an enrolled (or previewing staff) view of everything
     * posted, newest first.
     */
    public function index(Course $course): View
    {
        $this->authorize('viewAnnouncements', $course);

        return view('learn.announcements', [
            'course' => $course,
            'announcements' => $course->announcements()->with('author')->get(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('manageAnnouncements', $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $announcement = $course->announcements()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
        ]);

        $students = User::query()
            ->whereHas('enrollments', fn ($q) => $q->where('course_id', $course->id)
                ->whereIn('status', [EnrollmentStatus::Active->value, EnrollmentStatus::Completed->value]))
            ->get();

        Notification::send($students, new CourseAnnouncementNotification($announcement));

        return back()->with('status', 'Announcement posted — enrolled students have been notified.');
    }

    public function destroy(Course $course, CourseAnnouncement $announcement): RedirectResponse
    {
        $this->authorize('manageAnnouncements', $course);
        abort_unless($announcement->course_id === $course->id, 404);

        $announcement->delete();

        return back()->with('status', 'Announcement removed.');
    }
}
