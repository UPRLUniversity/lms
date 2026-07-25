<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Course;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Batched by SendPendingEnrollmentDigest (every 15 minutes): one notification per
 * instructor per course, however many pending requests arrived since the last run —
 * never one e-mail per request.
 */
class PendingEnrollmentDigestNotification extends UprlNotification
{
    public function __construct(public Course $course, public int $count) {}

    public static function type(): NotificationType
    {
        return NotificationType::NewPendingEnrollment;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $word = $this->count === 1 ? 'request is' : 'requests are';

        return (new MailMessage)
            ->subject("{$this->count} new enrolment {$word} waiting · {$this->course->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->count} new enrolment {$word} waiting for your decision in \"{$this->course->title}\".")
            ->action('Open the approval queue', route('enrollments.approvals'))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $word = $this->count === 1 ? 'request' : 'requests';

        return [
            'title' => 'New pending enrollment',
            'body' => "{$this->count} new {$word} in \"{$this->course->title}\".",
            'url' => route('enrollments.approvals'),
        ];
    }
}
