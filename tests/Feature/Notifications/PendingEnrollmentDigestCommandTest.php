<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Notifications\PendingEnrollmentDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PendingEnrollmentDigestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_batches_every_pending_request_into_one_notification_per_course(): void
    {
        Notification::fake();

        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->approvalMode()->withInstructor($instructor)->create();

        Enrollment::factory()->pending()->create(['user_id' => $this->userWithRole(Role::Student->value)->id, 'course_id' => $course->id]);
        Enrollment::factory()->pending()->create(['user_id' => $this->userWithRole(Role::Student->value)->id, 'course_id' => $course->id]);
        Enrollment::factory()->pending()->create(['user_id' => $this->userWithRole(Role::Student->value)->id, 'course_id' => $course->id]);

        $this->artisan('notifications:pending-enrollment-digest')->assertSuccessful();

        Notification::assertSentToTimes($instructor, PendingEnrollmentDigestNotification::class, 1);
        Notification::assertSentTo($instructor, PendingEnrollmentDigestNotification::class, fn ($n) => $n->count === 3);

        // A second run with nothing new digests nothing further.
        $this->artisan('notifications:pending-enrollment-digest')->assertSuccessful();
        Notification::assertSentToTimes($instructor, PendingEnrollmentDigestNotification::class, 1);
    }

    public function test_a_request_that_arrives_after_a_digest_is_picked_up_by_the_next_run(): void
    {
        Notification::fake();

        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->approvalMode()->withInstructor($instructor)->create();
        Enrollment::factory()->pending()->create(['user_id' => $this->userWithRole(Role::Student->value)->id, 'course_id' => $course->id]);

        $this->artisan('notifications:pending-enrollment-digest');
        Notification::assertSentToTimes($instructor, PendingEnrollmentDigestNotification::class, 1);

        Enrollment::factory()->pending()->create(['user_id' => $this->userWithRole(Role::Student->value)->id, 'course_id' => $course->id]);
        $this->artisan('notifications:pending-enrollment-digest');

        Notification::assertSentToTimes($instructor, PendingEnrollmentDigestNotification::class, 2);
    }
}
