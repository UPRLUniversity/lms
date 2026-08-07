<?php

namespace Tests\Feature;

use App\Models\CourseAnnouncement;
use App\Models\User;
use App\Notifications\Auth\QueuedResetPassword;
use App\Notifications\Auth\QueuedVerifyEmail;
use App\Notifications\CourseAnnouncementNotification;
use App\Notifications\DailyDigestNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Against a real SMTP host a single send costs seconds, so any notification that
 * goes out during a web request must be queued. This was invisible for as long as
 * MAIL_MAILER=log made every "send" a local file write; once real delivery was
 * switched on, an unqueued send became multi-second dead time in the request — and
 * on the fan-out paths, an outright timeout part-way through the recipient list.
 *
 * These tests pin the queueing itself rather than any one message's contents.
 */
class QueuedMailTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The framework ships VerifyEmail and ResetPassword unqueued. We deliberately
     * substitute queued subclasses (User::sendEmailVerificationNotification /
     * sendPasswordResetNotification); reverting to the framework classes would put
     * an SMTP round-trip back inside registration and "forgot password".
     */
    public function test_auth_notifications_are_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new QueuedVerifyEmail);
        $this->assertInstanceOf(ShouldQueue::class, new QueuedResetPassword('token'));
    }

    public function test_registering_queues_the_verification_mail(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();

        $this->post('/register', [
            'name' => 'Queued Learner',
            'email' => 'queued@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'queued@example.com')->firstOrFail();

        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_forgotten_password_queues_the_reset_mail(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    /**
     * The fan-out that actually hurt: one message per enrolled student, dispatched
     * inside the POST that creates the announcement. Queued, the request cost is
     * flat regardless of class size.
     */
    public function test_the_course_announcement_fan_out_is_queued(): void
    {
        $this->assertInstanceOf(
            ShouldQueue::class,
            new CourseAnnouncementNotification(new CourseAnnouncement),
        );
    }

    /**
     * The shared mail host stalls mid-handshake often enough that a single attempt
     * loses real messages. Every queued notification must retry; a verification or
     * reset link dropped on the first stall looks exactly like the delivery bug
     * this work started from.
     */
    #[DataProvider('queuedNotifications')]
    public function test_queued_notifications_retry_transient_smtp_failures(object $notification): void
    {
        $this->assertSame(3, $notification->tries);
        $this->assertSame([60, 300], $notification->backoff);
    }

    /**
     * @return array<string, array{0: object}>
     */
    public static function queuedNotifications(): array
    {
        return [
            'verify email' => [new QueuedVerifyEmail],
            'reset password' => [new QueuedResetPassword('token')],
            'course announcement' => [new CourseAnnouncementNotification(new CourseAnnouncement)],
            'daily digest' => [new DailyDigestNotification([])],
        ];
    }
}
