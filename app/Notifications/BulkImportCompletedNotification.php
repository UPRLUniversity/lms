<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

class BulkImportCompletedNotification extends UprlNotification
{
    /**
     * @param  array{imported: int, skipped: int, total: int}  $result
     * @param  string  $noun  what was imported, singular ("enrolment", "question").
     *                        Defaults to enrolment for the original importer, which
     *                        predates this being shared by every bulk import.
     */
    public function __construct(public array $result, public string $noun = 'enrolment') {}

    public static function type(): NotificationType
    {
        return NotificationType::BulkImportCompleted;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plural = Str::plural($this->noun);

        return (new MailMessage)
            ->subject('Bulk '.$plural.' import completed')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$plural} import has finished processing.")
            ->line("Imported: {$this->result['imported']} · Skipped: {$this->result['skipped']} · Total rows: {$this->result['total']}")
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => Str::ucfirst(Str::plural($this->noun)).' import completed',
            'body' => "Imported {$this->result['imported']} of {$this->result['total']} rows ({$this->result['skipped']} skipped).",
            'url' => null,
        ];
    }
}
