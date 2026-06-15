<?php

namespace App\Notifications;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Queued e-mail carrying the signed, expiring acceptance link. Queued so issuing
 * an invitation never blocks the admin's request (constitution: mail is queued).
 */
class UserInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected UserInvitation $invitation,
        protected string $rawToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'invitations.accept',
            $this->invitation->expires_at,
            ['invitation' => $this->invitation->id, 'token' => $this->rawToken],
        );

        return (new MailMessage)
            ->subject('You have been invited to '.config('brand.university'))
            ->greeting('Hello '.$this->invitation->name.',')
            ->line('An administrator has invited you to join '.config('brand.name').
                ' as a '.$this->invitation->role->label().'.')
            ->line('Click the button below to set your password and activate your account.')
            ->action('Accept invitation', $url)
            ->line('This invitation expires '.$this->invitation->expires_at->diffForHumans().
                ' and can only be used once.')
            ->salutation(config('brand.motto'));
    }
}
