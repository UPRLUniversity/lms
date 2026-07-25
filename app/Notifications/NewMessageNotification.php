<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Message;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Sent to a conversation's other participants when a new message arrives — but only to
 * those who were already caught up (MessagingService suppresses a fresh notification
 * while someone still has unread messages in the same thread, so a burst doesn't spam
 * the bell). Honours each recipient's Section-8 channel preferences via UprlNotification.
 */
class NewMessageNotification extends UprlNotification
{
    public function __construct(public Message $message) {}

    public static function type(): NotificationType
    {
        return NotificationType::NewMessage;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $conversation = $this->message->conversation;
        $sender = $this->message->sender;
        $label = $conversation->isGroup()
            ? ($conversation->subject ?: 'a group conversation')
            : 'a message';

        return (new MailMessage)
            ->subject("New message from {$sender->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$sender->name} sent you {$label}:")
            ->line('"'.$this->preview().'"')
            ->action('Read and reply', route('messages.show', $conversation))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $conversation = $this->message->conversation;
        $sender = $this->message->sender;

        return [
            'title' => $conversation->isGroup()
                ? 'New message in '.($conversation->subject ?: 'a group')
                : 'New message from '.$sender->name,
            'body' => $this->preview(),
            'url' => route('messages.show', $conversation),
        ];
    }

    private function preview(): string
    {
        return Str::limit(trim(strip_tags((string) $this->message->body)), 100);
    }
}
