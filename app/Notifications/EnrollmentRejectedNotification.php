<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Enrollment;
use Illuminate\Notifications\Messages\MailMessage;

class EnrollmentRejectedNotification extends UprlNotification
{
    public function __construct(public Enrollment $enrollment) {}

    public static function type(): NotificationType
    {
        return NotificationType::EnrollmentRejected;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->enrollment->course;
        $mail = (new MailMessage)
            ->subject("About your \"{$course->title}\" enrolment request")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your enrolment request for \"{$course->title}\" was not approved this time.");

        if ($this->enrollment->decision_note) {
            $mail->line('Note from the reviewer: '.$this->enrollment->decision_note);
        }

        return $mail->action('Browse the catalogue', route('catalogue.index'))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->enrollment->course;

        return [
            'title' => 'Enrollment declined',
            'body' => "Your request to join \"{$course->title}\" was declined.",
            'url' => route('catalogue.show', $course),
        ];
    }
}
