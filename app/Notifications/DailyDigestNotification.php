<?php

namespace App\Notifications;

use App\Notifications\Concerns\RetriesTransientMailFailures;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The bundled e-mail SendNotificationDigest sends once a day to a student who has
 * opted into learning_preferences.email_digest, folding every digestible
 * notification since the last run into one message instead of one per event.
 * Mail-only by design — it doesn't itself appear in the bell (the individual
 * database rows it summarises already do, per the recipient's in-app preference).
 */
class DailyDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RetriesTransientMailFailures;

    /**
     * @param  array<int, array{title: string, body: string, url: string|null}>  $items
     */
    public function __construct(public array $items) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->items);

        $mail = (new MailMessage)
            ->subject("Your daily digest — {$count} update".($count === 1 ? '' : 's'))
            ->greeting("Hello {$notifiable->name},")
            ->line("Here's what happened since your last digest:");

        foreach ($this->items as $item) {
            $mail->line('**'.$item['title'].'** — '.$item['body']);
        }

        return $mail
            ->line('Prefer these one at a time? Turn off the digest on your profile page.')
            ->action('Open notifications', route('notifications.index'))
            ->salutation(config('brand.motto'));
    }
}
