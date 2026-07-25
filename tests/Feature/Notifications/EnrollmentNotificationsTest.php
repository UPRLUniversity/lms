<?php

namespace Tests\Feature\Notifications;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Notifications\EnrollmentApprovedNotification;
use App\Notifications\EnrollmentConfirmedNotification;
use App\Notifications\EnrollmentRejectedNotification;
use App\Notifications\WaitlistPromotedNotification;
use App\Services\Courses\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EnrollmentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_enrolling_into_an_open_course_sends_a_confirmation(): void
    {
        Notification::fake();

        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        app(EnrollmentService::class)->selfEnroll($student, $course);

        Notification::assertSentTo($student, EnrollmentConfirmedNotification::class);
    }

    public function test_approving_a_pending_request_notifies_the_student(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->approvalMode()->create();
        $enrollment = Enrollment::factory()->pending()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($admin)->post(route('enrollments.approve', $enrollment))->assertRedirect();

        Notification::assertSentTo($student, EnrollmentApprovedNotification::class);
        Notification::assertNotSentTo($student, EnrollmentConfirmedNotification::class);
    }

    public function test_bulk_approve_also_notifies_every_approved_student(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->approvalMode()->create();
        $a = Enrollment::factory()->pending()->create(['user_id' => $this->userWithRole(Role::Student->value)->id, 'course_id' => $course->id]);
        $b = Enrollment::factory()->pending()->create(['user_id' => $this->userWithRole(Role::Student->value)->id, 'course_id' => $course->id]);

        $this->actingAs($admin)->post(route('enrollments.bulk-approve'), ['ids' => [$a->id, $b->id]]);

        Notification::assertSentTo($a->user, EnrollmentApprovedNotification::class);
        Notification::assertSentTo($b->user, EnrollmentApprovedNotification::class);
    }

    public function test_rejecting_a_pending_request_notifies_the_student(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->approvalMode()->create();
        $enrollment = Enrollment::factory()->pending()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($admin)
            ->post(route('enrollments.reject', $enrollment), ['note' => 'Prerequisite not met.'])
            ->assertRedirect();

        Notification::assertSentTo($student, EnrollmentRejectedNotification::class, function ($notification) {
            return $notification->enrollment->decision_note === 'Prerequisite not met.';
        });
    }

    public function test_waitlist_promotion_notifies_the_promoted_student(): void
    {
        Notification::fake();

        $course = Course::factory()->published()->withCapacity(1)->create();
        $service = app(EnrollmentService::class);

        $seated = $this->userWithRole(Role::Student->value);
        $service->selfEnroll($seated, $course);

        $waiting = $this->userWithRole(Role::Student->value);
        $service->selfEnroll($waiting, $course);
        $this->assertSame(EnrollmentStatus::Waitlisted, $course->enrollmentFor($waiting)->status);

        // The seated student withdraws, freeing a seat for the waitlisted one.
        $service->withdraw($course->enrollmentFor($seated));

        Notification::assertSentTo($waiting, WaitlistPromotedNotification::class);
    }

    public function test_turning_a_types_email_off_stops_the_email_but_in_app_continues(): void
    {
        Notification::fake();

        $admin = $this->userWithRole(Role::Admin->value);
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->approvalMode()->create();
        $enrollment = Enrollment::factory()->pending()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        // EnrollmentApproved is critical, so in-app can't be disabled — but e-mail can.
        $student->setNotificationPreference(\App\Enums\NotificationType::EnrollmentApproved, ['email' => false]);
        $student->save();

        $this->actingAs($admin)->post(route('enrollments.approve', $enrollment));

        Notification::assertSentTo($student, EnrollmentApprovedNotification::class, function ($notification, $channels) {
            return in_array('database', $channels, true) && ! in_array('mail', $channels, true);
        });
    }

    public function test_a_critical_types_in_app_channel_cannot_be_disabled(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $student->setNotificationPreference(\App\Enums\NotificationType::EnrollmentApproved, ['in_app' => false]);
        $student->save();

        $this->assertTrue($student->fresh()->notifiesInApp(\App\Enums\NotificationType::EnrollmentApproved));
    }
}
