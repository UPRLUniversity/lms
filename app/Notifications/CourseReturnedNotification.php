<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Course;
use Illuminate\Notifications\Messages\MailMessage;

class CourseReturnedNotification extends UprlNotification
{
    public function __construct(public Course $course, public string $note) {}

    public static function type(): NotificationType
    {
        return NotificationType::CourseReturned;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("\"{$this->course->title}\" was returned with a note")
            ->greeting("Hello {$notifiable->name},")
            ->line("\"{$this->course->title}\" was returned to draft with a note from the reviewer:")
            ->line($this->note)
            ->action('Open the course builder', route('courses.edit', $this->course))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Course returned with note',
            'body' => "\"{$this->course->title}\": {$this->note}",
            'url' => route('courses.edit', $this->course),
        ];
    }
}
