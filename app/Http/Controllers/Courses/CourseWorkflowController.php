<?php

namespace App\Http\Controllers\Courses;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Courses\CoursePublishingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The draft → review → published workflow. Instructors submit their own drafts;
 * admins publish, return-with-note, archive and restore. All status writes go
 * through CoursePublishingService, which guards the transition table and the
 * publish-readiness rules.
 */
class CourseWorkflowController extends Controller
{
    public function __construct(private CoursePublishingService $publishing) {}

    /**
     * Instructor submits a draft for review.
     */
    public function submit(Course $course): RedirectResponse
    {
        $this->authorize('submitForReview', $course);

        $blockers = $this->publishing->publishBlockers($course);
        if ($blockers !== []) {
            return back()->withErrors(['publish' => $blockers]);
        }

        $this->publishing->submitForReview($course);

        return back()->with('status', 'Submitted for review. An admin will take a look shortly.');
    }

    /**
     * Admin approves a course in review and publishes it.
     */
    public function publish(Course $course): RedirectResponse
    {
        $this->authorize('review', $course);

        $this->publishing->publish($course);

        return back()->with('status', "“{$course->title}” is now published and live in the catalogue.");
    }

    /**
     * Admin returns a course to the instructor with a required note.
     */
    public function returnToDraft(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('review', $course);

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], ['review_note' => 'note']);

        $this->publishing->returnToDraft($course, $validated['review_note']);

        return back()->with('status', 'Returned to the instructor with your note.');
    }

    /**
     * Admin archives a published course (hidden from the catalogue; enrollees keep access).
     */
    public function archive(Course $course): RedirectResponse
    {
        $this->authorize('review', $course);

        $this->publishing->archive($course);

        return back()->with('status', "“{$course->title}” was archived.");
    }

    /**
     * Admin restores an archived course back to the catalogue.
     */
    public function restore(Course $course): RedirectResponse
    {
        $this->authorize('review', $course);

        $this->publishing->restore($course);

        return back()->with('status', "“{$course->title}” was restored.");
    }
}
