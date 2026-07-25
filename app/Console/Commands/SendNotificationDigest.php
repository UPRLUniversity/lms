<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\DailyDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Once a day, bundles every digestible notification a student has opted (via
 * learning_preferences.email_digest) to receive as a summary instead of
 * individually, into one e-mail — see UprlNotification::via(), which withholds
 * immediate mail for digestible types when this preference is on. digested_at is
 * the idempotency flag: a row is folded into exactly one digest, ever.
 */
class SendNotificationDigest extends Command
{
    protected $signature = 'notifications:digest';

    protected $description = 'E-mail each digest-opted user a daily summary of their digestible notifications';

    public function handle(): int
    {
        $sent = 0;

        User::query()->chunkById(100, function ($users) use (&$sent) {
            foreach ($users as $user) {
                if (! $user->wantsEmailDigest()) {
                    continue;
                }

                $rows = $user->notifications()
                    ->whereNull('digested_at')
                    ->get()
                    ->filter(function (DatabaseNotification $notification) use ($user) {
                        $class = $notification->type;
                        if (! is_subclass_of($class, \App\Notifications\UprlNotification::class)) {
                            return false;
                        }

                        /** @var NotificationType $type */
                        $type = $class::type();

                        return $type->isDigestible() && $user->notifiesByEmail($type);
                    });

                if ($rows->isEmpty()) {
                    continue;
                }

                $items = $rows->map(fn (DatabaseNotification $n) => [
                    'title' => $n->data['title'] ?? 'Notification',
                    'body' => $n->data['body'] ?? '',
                    'url' => $n->data['url'] ?? null,
                ])->values()->all();

                $user->notify(new DailyDigestNotification($items));

                DatabaseNotification::whereIn('id', $rows->pluck('id'))->update(['digested_at' => now()]);

                $sent++;
            }
        });

        $this->info("Sent {$sent} digest e-mail(s).");

        return self::SUCCESS;
    }
}
