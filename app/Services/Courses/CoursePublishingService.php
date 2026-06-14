<?php

namespace App\Services\Courses;

use App\Enums\CourseStatus;
use App\Models\Course;
use Illuminate\Validation\ValidationException;

/**
 * Owns the course publishing workflow: the draft → review → published lifecycle,
 * the rules that gate a publish, and the (single) place status is ever written.
 * Controllers call these methods; they never set course.status directly.
 */
class CoursePublishingService
{
    /**
     * Human-readable reasons a course is not yet publishable. Empty array ⇒ ready.
     *
     * Publish validation (per the section spec): ≥1 module, ≥1 lesson, a cover
     * image and a summary.
     *
     * @return array<int, string>
     */
    public function publishBlockers(Course $course): array
    {
        $blockers = [];

        $moduleCount = $course->modules()->count();
        if ($moduleCount < 1) {
            $blockers[] = 'Add at least one module.';
        }

        if ($course->lessons()->count() < 1) {
            $blockers[] = 'Add at least one lesson.';
        }

        if (blank($course->summary)) {
            $blockers[] = 'Write a short summary.';
        }

        if ($course->cover() === null) {
            $blockers[] = 'Upload a cover image.';
        }

        return $blockers;
    }

    public function canPublish(Course $course): bool
    {
        return $this->publishBlockers($course) === [];
    }

    /**
     * Instructor submits a draft for admin review.
     */
    public function submitForReview(Course $course): void
    {
        $this->transition($course, CourseStatus::Review);

        // Clear any previous return note now the instructor has re-submitted.
        $course->forceFill(['review_note' => null])->save();
    }

    /**
     * Admin approves a course in review and publishes it. Re-checks the publish
     * rules so an empty course can never go live.
     */
    public function publish(Course $course): void
    {
        $blockers = $this->publishBlockers($course);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'publish' => $blockers,
            ]);
        }

        $this->transition($course, CourseStatus::Published);

        $course->forceFill([
            'review_note' => null,
            'published_at' => $course->published_at ?? now(),
        ])->save();
    }

    /**
     * Admin returns a course in review to the instructor with a required note.
     */
    public function returnToDraft(Course $course, string $note): void
    {
        $note = trim($note);

        if ($note === '') {
            throw ValidationException::withMessages([
                'review_note' => 'A note explaining the requested changes is required.',
            ]);
        }

        $this->transition($course, CourseStatus::Draft);

        $course->forceFill(['review_note' => $note])->save();
    }

    /**
     * Archive a published course: it leaves the catalogue but enrollees keep access.
     */
    public function archive(Course $course): void
    {
        $this->transition($course, CourseStatus::Archived);
        $course->save();
    }

    /**
     * Restore an archived course back to the catalogue.
     */
    public function restore(Course $course): void
    {
        $this->transition($course, CourseStatus::Published);
        $course->forceFill(['published_at' => $course->published_at ?? now()])->save();
    }

    /**
     * Guard a status change against the enum's transition table, then apply it.
     */
    private function transition(Course $course, CourseStatus $target): void
    {
        $current = $course->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "A {$current->label()} course cannot move to {$target->label()}.",
            ]);
        }

        $course->status = $target;
    }
}
