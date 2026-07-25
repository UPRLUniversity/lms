<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Notifications\AssignmentGradedNotification;
use App\Notifications\DailyDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The daily digest reads real database-channel notification rows (Notification::fake
 * would swallow them before they're written), so these tests let the trigger
 * notification send for real and only fake around the digest command itself.
 */
class DigestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_digest_opted_student_gets_one_bundled_email_instead_of_immediate_mail(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $student->learning_preferences = ['email_digest' => true];
        $student->save();

        $course = Course::factory()->published()->create();
        $assignment = Assignment::factory()->published()->create(['course_id' => $course->id, 'max_points' => 10]);
        $submission = Submission::factory()->create(['assignment_id' => $assignment->id, 'user_id' => $student->id]);

        // Real send: AssignmentGraded is digestible, so only 'database' fires — no
        // immediate mail — and the row is written for the digest command to find.
        $student->notify(new AssignmentGradedNotification($submission));

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $student->id, 'digested_at' => null]);

        Notification::fake();
        $this->artisan('notifications:digest')->assertSuccessful();

        Notification::assertSentTo($student, DailyDigestNotification::class, function ($notification) {
            return count($notification->items) === 1;
        });

        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $student->id, 'digested_at' => null]);
    }

    public function test_a_row_is_never_digested_twice(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $student->learning_preferences = ['email_digest' => true];
        $student->save();

        $course = Course::factory()->published()->create();
        $assignment = Assignment::factory()->published()->create(['course_id' => $course->id]);
        $submission = Submission::factory()->create(['assignment_id' => $assignment->id, 'user_id' => $student->id]);
        $student->notify(new AssignmentGradedNotification($submission));

        Notification::fake();
        $this->artisan('notifications:digest');
        Notification::assertSentToTimes($student, DailyDigestNotification::class, 1);

        // Nothing new to digest — the second run sends nothing further.
        $this->artisan('notifications:digest');
        Notification::assertSentToTimes($student, DailyDigestNotification::class, 1);
    }

    public function test_a_student_without_the_digest_preference_is_skipped(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        // email_digest defaults to false.

        $course = Course::factory()->published()->create();
        $assignment = Assignment::factory()->published()->create(['course_id' => $course->id]);
        $submission = Submission::factory()->create(['assignment_id' => $assignment->id, 'user_id' => $student->id]);
        $student->notify(new AssignmentGradedNotification($submission));

        Notification::fake();
        $this->artisan('notifications:digest');

        Notification::assertNotSentTo($student, DailyDigestNotification::class);
    }
}
