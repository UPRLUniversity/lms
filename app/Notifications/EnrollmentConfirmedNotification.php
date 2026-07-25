<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Enrollment;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent the moment a self- or admin-enrolment lands directly on Active (open course,
 * or a staff override) — not for a pending request, which waits for approve/reject.
 */
class EnrollmentConfirmedNotification extends UprlNotification
{
    public function __construct(public Enrollment $enrollment) {}

    public static function type(): NotificationType
    {
        return NotificationType::EnrollmentConfirmed;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject("You're enrolled in {$course->title}")
            ->greeting("Welcome aboard, {$notifiable->name}!")
            ->line("Your enrolment in \"{$course->title}\" is confirmed — you have full access.")
            ->action('Start learning', route('learn.resume', $course))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        return [
            'title' => 'Enrollment confirmed',
            'body' => "You're enrolled in \"{$course->title}\".",
            'url' => route('learn.resume', $course),
        ];
    }
}
