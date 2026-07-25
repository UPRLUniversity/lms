<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Enrollment;
use Illuminate\Notifications\Messages\MailMessage;

class EnrollmentApprovedNotification extends UprlNotification
{
    public function __construct(public Enrollment $enrollment) {}

    public static function type(): NotificationType
    {
        return NotificationType::EnrollmentApproved;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject("You're enrolled in {$course->title}")
            ->greeting("Good news, {$notifiable->name}!")
            ->line("Your enrolment request for \"{$course->title}\" has been approved.")
            ->action('Start learning', route('learn.resume', $course))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        return [
            'title' => 'Enrollment approved',
            'body' => "You're in — \"{$course->title}\" has been approved.",
            'url' => route('learn.resume', $course),
        ];
    }
}
