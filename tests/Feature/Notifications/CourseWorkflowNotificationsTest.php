<?php

namespace Tests\Feature\Notifications;

use App\Enums\CourseStatus;
use App\Enums\MediaPurpose;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Media;
use App\Models\Module;
use App\Models\User;
use App\Notifications\CourseApprovedNotification;
use App\Notifications\CourseReturnedNotification;
use App\Notifications\CourseSubmittedForReviewNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CourseWorkflowNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function publishableCourse(User $instructor): Course
    {
        $course = Course::factory()->withInstructor($instructor)->create([
            'created_by' => $instructor->id,
            'status' => CourseStatus::Draft->value,
            'summary' => 'A real summary.',
        ]);

        $module = Module::factory()->for($course)->create();
        Lesson::factory()->for($module)->create();
        Media::factory()->for($course, 'mediable')->create(['purpose' => MediaPurpose::CourseCovers]);

        return $course->refresh();
    }

    public function test_submitting_for_review_notifies_every_admin(): void
    {
        Notification::fake();

        $admin1 = $this->userWithRole(Role::Admin->value);
        $admin2 = $this->userWithRole(Role::Admin->value);
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = $this->publishableCourse($instructor);

        $this->actingAs($instructor)->post(route('courses.submit', $course));

        Notification::assertSentTo($admin1, CourseSubmittedForReviewNotification::class);
        Notification::assertSentTo($admin2, CourseSubmittedForReviewNotification::class);
    }

    public function test_publishing_notifies_the_instructors(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(Role::Admin->value);
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = $this->publishableCourse($instructor);
        $course->update(['status' => CourseStatus::Review->value]);

        $this->actingAs($admin)->post(route('courses.publish', $course));

        Notification::assertSentTo($instructor, CourseApprovedNotification::class);
    }

    public function test_returning_to_draft_notifies_the_instructors_with_the_note(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(Role::Admin->value);
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = $this->publishableCourse($instructor);
        $course->update(['status' => CourseStatus::Review->value]);

        $this->actingAs($admin)->post(route('courses.return', $course), [
            'review_note' => 'Please expand Module 2.',
        ]);

        Notification::assertSentTo($instructor, CourseReturnedNotification::class, function ($notification) {
            return $notification->note === 'Please expand Module 2.';
        });
    }
}
