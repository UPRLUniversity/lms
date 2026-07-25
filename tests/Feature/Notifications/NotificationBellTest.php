<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Notifications\EnrollmentConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function notifiedStudent(): array
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);
        $student->notify(new EnrollmentConfirmedNotification($enrollment));

        return [$student, $student->notifications()->firstOrFail()];
    }

    public function test_recent_endpoint_reports_unread_count_and_items(): void
    {
        [$student] = $this->notifiedStudent();

        $response = $this->actingAs($student)->getJson(route('notifications.recent'));

        $response->assertOk()->assertJson(['unread_count' => 1]);
        $this->assertCount(1, $response->json('notifications'));
        $this->assertSame('Enrollment confirmed', $response->json('notifications.0.title'));
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_its_target(): void
    {
        [$student, $notification] = $this->notifiedStudent();

        $this->assertNull($notification->read_at);

        $this->actingAs($student)->get(route('notifications.open', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_open_someone_elses_notification(): void
    {
        [, $notification] = $this->notifiedStudent();
        $outsider = $this->userWithRole(Role::Student->value);

        $this->actingAs($outsider)->get(route('notifications.open', $notification))->assertNotFound();
    }

    public function test_mark_all_read_clears_the_unread_count(): void
    {
        [$student] = $this->notifiedStudent();

        $this->actingAs($student)->post(route('notifications.mark-all-read'))->assertRedirect();

        $this->assertSame(0, $student->unreadNotifications()->count());
    }

    public function test_the_full_history_page_renders(): void
    {
        [$student] = $this->notifiedStudent();

        $this->actingAs($student)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Enrollment confirmed');
    }
}
