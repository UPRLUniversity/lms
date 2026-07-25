<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Enrollment;
use Illuminate\Notifications\Messages\MailMessage;

class WaitlistPromotedNotification extends UprlNotification
{
    public function __construct(public Enrollment $enrollment) {}

    public static function type(): NotificationType
    {
        return NotificationType::WaitlistPromoted;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->enrollment->course;

        return (new MailMessage)
            ->subject("A seat opened up in {$course->title}")
            ->greeting("Good news, {$notifiable->name}!")
            ->line("A seat opened up in \"{$course->title}\" and you've been moved off the waitlist.")
            ->action('Start learning', route('learn.resume', $course))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        return [
            'title' => 'Waitlist promotion',
            'body' => "You've been promoted off the waitlist for \"{$course->title}\".",
            'url' => route('learn.resume', $course),
        ];
    }
}
