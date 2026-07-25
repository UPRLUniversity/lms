<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Submission;
use Illuminate\Notifications\Messages\MailMessage;

class AssignmentReturnedNotification extends UprlNotification
{
    public function __construct(public Submission $submission) {}

    public static function type(): NotificationType
    {
        return NotificationType::AssignmentReturned;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assignment = $this->submission->assignment;

        return (new MailMessage)
            ->subject("\"{$assignment->title}\" was returned for changes")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your submission for \"{$assignment->title}\" was returned — another version is needed.")
            ->line('Note: '.$this->submission->return_note)
            ->action('Resubmit', route('assignments.show', [$assignment->course, $assignment]))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $assignment = $this->submission->assignment;

        return [
            'title' => 'Assignment returned',
            'body' => "\"{$assignment->title}\" was returned for another version.",
            'url' => route('assignments.show', [$assignment->course, $assignment]),
        ];
    }
}
