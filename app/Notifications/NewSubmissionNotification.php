<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Submission;
use Illuminate\Notifications\Messages\MailMessage;

class NewSubmissionNotification extends UprlNotification
{
    public function __construct(public Submission $submission) {}

    public static function type(): NotificationType
    {
        return NotificationType::NewSubmission;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assignment = $this->submission->assignment;
        $student = $this->submission->user;

        return (new MailMessage)
            ->subject("New submission to grade · {$assignment->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$student->name} submitted \"{$assignment->title}\" and it's ready to grade.")
            ->action('Open grading queue', route('grading.assignments.show', $this->submission))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $assignment = $this->submission->assignment;
        $student = $this->submission->user;

        return [
            'title' => 'New submission to grade',
            'body' => "{$student->name} submitted \"{$assignment->title}\".",
            'url' => route('grading.assignments.show', $this->submission),
        ];
    }
}
