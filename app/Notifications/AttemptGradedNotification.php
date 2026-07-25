<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Attempt;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent when the last manually-graded item (essay / scenario-essay) on an attempt is
 * settled and it becomes fully graded (AttemptService::finalizeAfterGrading).
 */
class AttemptGradedNotification extends UprlNotification
{
    public function __construct(public Attempt $attempt) {}

    public static function type(): NotificationType
    {
        return NotificationType::AttemptGraded;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assessment = $this->attempt->assessment;
        $score = $this->attempt->percentage !== null ? "{$this->attempt->percentage}%" : null;

        return (new MailMessage)
            ->subject("\"{$assessment->title}\" has been graded")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your attempt at \"{$assessment->title}\" has been graded".($score ? " — {$score}." : '.'))
            ->action('View result', route('attempts.result', $this->attempt))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $assessment = $this->attempt->assessment;

        return [
            'title' => 'Exam graded',
            'body' => "\"{$assessment->title}\" has been graded.",
            'url' => route('attempts.result', $this->attempt),
        ];
    }
}
