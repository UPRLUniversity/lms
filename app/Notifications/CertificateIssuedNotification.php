<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Certificate;
use Illuminate\Notifications\Messages\MailMessage;

class CertificateIssuedNotification extends UprlNotification
{
    public function __construct(public Certificate $certificate) {}

    public static function type(): NotificationType
    {
        return NotificationType::CertificateIssued;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $course = $this->certificate->course;

        return (new MailMessage)
            ->subject("Your certificate for {$course->title} is ready")
            ->greeting("Congratulations, {$notifiable->name}!")
            ->line("You've completed \"{$course->title}\" and your certificate has been issued.")
            ->action('View certificate', route('certificates.mine'))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $course = $this->certificate->course;

        return [
            'title' => 'Certificate issued',
            'body' => "Your certificate for \"{$course->title}\" is ready.",
            'url' => route('certificates.mine'),
        ];
    }
}
