<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Course;
use Illuminate\Notifications\Messages\MailMessage;

class CourseSubmittedForReviewNotification extends UprlNotification
{
    public function __construct(public Course $course) {}

    public static function type(): NotificationType
    {
        return NotificationType::CourseSubmittedForReview;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $instructor = $this->course->leadInstructor();

        return (new MailMessage)
            ->subject("Course submitted for review: {$this->course->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line('"'.$this->course->title.'" was submitted for review'.($instructor ? " by {$instructor->name}" : '').'.')
            ->action('Review course', route('courses.edit', $this->course))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Course submitted for review',
            'body' => "\"{$this->course->title}\" is awaiting review.",
            'url' => route('courses.edit', $this->course),
        ];
    }
}
