<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

class BulkImportCompletedNotification extends UprlNotification
{
    /**
     * @param  array{imported: int, skipped: int, total: int}  $result
     */
    public function __construct(public array $result) {}

    public static function type(): NotificationType
    {
        return NotificationType::BulkImportCompleted;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bulk enrolment import completed')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your enrolment import has finished processing.')
            ->line("Imported: {$this->result['imported']} · Skipped: {$this->result['skipped']} · Total rows: {$this->result['total']}")
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Bulk import completed',
            'body' => "Imported {$this->result['imported']} of {$this->result['total']} rows ({$this->result['skipped']} skipped).",
            'url' => null,
        ];
    }
}
