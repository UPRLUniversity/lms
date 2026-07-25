<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Course;
use Illuminate\Notifications\Messages\MailMessage;

class CourseApprovedNotification extends UprlNotification
{
    public function __construct(public Course $course) {}

    public static function type(): NotificationType
    {
        return NotificationType::CourseApproved;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("\"{$this->course->title}\" is now published")
            ->greeting("Congratulations, {$notifiable->name}!")
            ->line("\"{$this->course->title}\" has been approved and is now live in the catalogue.")
            ->action('View course', route('catalogue.show', $this->course))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Course approved',
            'body' => "\"{$this->course->title}\" is now published.",
            'url' => route('courses.edit', $this->course),
        ];
    }
}
