<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Models\Enrollment;
use App\Notifications\CourseAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AnnouncementNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_an_announcement_notifies_active_and_completed_students_only(): void
    {
        Notification::fake();

        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);

        $active = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $active->id, 'course_id' => $course->id]);

        $completed = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->completed()->create(['user_id' => $completed->id, 'course_id' => $course->id]);

        $withdrawn = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->withdrawn()->create(['user_id' => $withdrawn->id, 'course_id' => $course->id]);

        $this->actingAs($instructor)->post(route('announcements.store', $course), [
            'title' => 'Reading week',
            'body' => '<p>No live session this week.</p>',
        ])->assertRedirect();

        $this->assertDatabaseHas('course_announcements', ['course_id' => $course->id, 'title' => 'Reading week']);

        Notification::assertSentTo($active, CourseAnnouncementNotification::class);
        Notification::assertSentTo($completed, CourseAnnouncementNotification::class);
        Notification::assertNotSentTo($withdrawn, CourseAnnouncementNotification::class);
    }

    public function test_a_co_instructor_cannot_post_on_another_courses_announcements(): void
    {
        $owner = $this->userWithRole(Role::Instructor->value);
        $outsider = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $owner->id]);

        $this->actingAs($outsider)->post(route('announcements.store', $course), [
            'title' => 'x', 'body' => '<p>x</p>',
        ])->assertForbidden();
    }

    public function test_enrolled_student_sees_the_announcement_on_the_learner_page(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $announcement = CourseAnnouncement::factory()->create([
            'course_id' => $course->id,
            'user_id' => $instructor->id,
            'title' => 'Welcome',
            'body' => '<p>Glad to have you.</p>',
        ]);

        $this->actingAs($student)->get(route('learn.announcements', $course))
            ->assertOk()
            ->assertSee('Welcome')
            ->assertSee('Glad to have you.', false);
    }

    public function test_a_non_enrolled_student_cannot_view_announcements(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        $outsider = $this->userWithRole(Role::Student->value);

        $this->actingAs($outsider)->get(route('learn.announcements', $course))->assertForbidden();
    }
}
