<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared plumbing for every notification in the Section 8 catalogue: channel
 * selection honours the recipient's per-type preferences (learning_preferences,
 * see User::notifiesInApp/notifiesByEmail), and a digestible type whose owner has
 * opted into the daily digest is withheld from immediate mail — SendNotificationDigest
 * folds it into a bundled e-mail later instead. Queued like every other notification
 * in the app (constitution: mail never blocks a request).
 *
 * Concrete classes only need to implement type() + toMail() + toArray().
 */
abstract class UprlNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The catalogue entry this notification belongs to. Static so non-instance
     * callers (the digest command, reading a stored `type` class name) can ask
     * "is this digestible / whose preference key is this" without building one.
     */
    abstract public static function type(): NotificationType;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $type = static::type();
        $channels = [];

        if (! $notifiable instanceof User) {
            return ['database'];
        }

        if ($notifiable->notifiesInApp($type)) {
            $channels[] = 'database';
        }

        $digestedAway = $type->isDigestible() && $notifiable->wantsEmailDigest();

        if ($notifiable->notifiesByEmail($type) && ! $digestedAway) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    abstract public function toMail(object $notifiable): MailMessage;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;
}
