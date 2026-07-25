<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Notifications\AssignmentDueSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DueSoonCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminds_enrolled_students_who_have_not_submitted_exactly_once(): void
    {
        Notification::fake();

        $course = Course::factory()->published()->create();
        $assignment = Assignment::factory()->published()->create([
            'course_id' => $course->id,
            'due_at' => now()->addHours(30),
        ]);

        $pending = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $pending->id, 'course_id' => $course->id]);

        $alreadySubmitted = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $alreadySubmitted->id, 'course_id' => $course->id]);
        Submission::factory()->create(['assignment_id' => $assignment->id, 'user_id' => $alreadySubmitted->id]);

        // Due far in the future — outside the 48h window, must not be reminded.
        Assignment::factory()->published()->create([
            'course_id' => $course->id,
            'due_at' => now()->addDays(5),
        ]);

        $this->artisan('notifications:due-soon')->assertSuccessful();

        Notification::assertSentTo($pending, AssignmentDueSoonNotification::class);
        Notification::assertNotSentTo($alreadySubmitted, AssignmentDueSoonNotification::class);
        $this->assertDatabaseCount('assignment_due_reminders', 1);

        // Running again the same hour never double-reminds — the idempotency flag holds.
        $this->artisan('notifications:due-soon')->assertSuccessful();
        Notification::assertSentToTimes($pending, AssignmentDueSoonNotification::class, 1);
        $this->assertDatabaseCount('assignment_due_reminders', 1);
    }
}
