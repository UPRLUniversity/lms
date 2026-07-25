<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Submission;
use Illuminate\Notifications\Messages\MailMessage;

class AssignmentGradedNotification extends UprlNotification
{
    public function __construct(public Submission $submission) {}

    public static function type(): NotificationType
    {
        return NotificationType::AssignmentGraded;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $assignment = $this->submission->assignment;
        $grade = $this->submission->grade;
        $score = $grade ? number_format((float) $grade->points_total, 1).'/'.number_format((float) $assignment->max_points, 1) : null;

        $mail = (new MailMessage)
            ->subject("\"{$assignment->title}\" has been graded")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your submission for \"{$assignment->title}\" has been graded".($score ? " — {$score} points." : '.'));

        if ($grade?->feedback) {
            $mail->line('Feedback: '.$grade->feedback);
        }

        return $mail->action('View feedback', route('submissions.show', $this->submission))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $assignment = $this->submission->assignment;

        return [
            'title' => 'Assignment graded',
            'body' => "\"{$assignment->title}\" has been graded.",
            'url' => route('submissions.show', $this->submission),
        ];
    }
}
