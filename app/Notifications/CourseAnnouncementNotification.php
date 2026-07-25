<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\CourseAnnouncement;
use Illuminate\Notifications\Messages\MailMessage;

class CourseAnnouncementNotification extends UprlNotification
{
    public function __construct(public CourseAnnouncement $announcement) {}

    public static function type(): NotificationType
    {
        return NotificationType::CourseAnnouncement;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->announcement->course;

        return (new MailMessage)
            ->subject("New announcement · {$course->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->announcement->author->name} posted a new announcement in \"{$course->title}\":")
            ->line('**'.$this->announcement->title.'**')
            ->line(strip_tags((string) $this->announcement->body))
            ->action('Read the full announcement', route('learn.announcements', $course))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->announcement->course;

        return [
            'title' => 'New announcement: '.$this->announcement->title,
            'body' => $course->title,
            'url' => route('learn.announcements', $course),
        ];
    }
}
