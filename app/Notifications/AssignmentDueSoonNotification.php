<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Assignment;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent by the notifications:due-soon scheduled command for students who haven't yet
 * submitted an assignment due within 48 hours. One-shot per (assignment, user) —
 * see assignment_due_reminders, the command's idempotency flag.
 */
class AssignmentDueSoonNotification extends UprlNotification
{
    public function __construct(public Assignment $assignment) {}

    public static function type(): NotificationType
    {
        return NotificationType::AssignmentDueSoon;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->assignment->course;

        return (new MailMessage)
            ->subject("\"{$this->assignment->title}\" is due soon")
            ->greeting("Hello {$notifiable->name},")
            ->line("\"{$this->assignment->title}\" (in {$course->title}) is due ".$this->assignment->due_at->diffForHumans().'.')
            ->action('Submit your work', route('assignments.show', [$course, $this->assignment]))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->assignment->course;

        return [
            'title' => 'Assignment due soon',
            'body' => "\"{$this->assignment->title}\" is due ".$this->assignment->due_at->diffForHumans().'.',
            'url' => route('assignments.show', [$course, $this->assignment]),
        ];
    }
}
