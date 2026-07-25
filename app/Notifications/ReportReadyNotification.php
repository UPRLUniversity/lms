<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\GeneratedReport;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the requester their queued report export has finished building and links to the
 * gated download. Delivered in-app only: it answers an action the user just took, so it
 * bypasses the per-type channel preferences (a report they asked for should never be
 * silently suppressed) and never needs an e-mail.
 */
class ReportReadyNotification extends UprlNotification
{
    public function __construct(public GeneratedReport $report) {}

    public static function type(): NotificationType
    {
        return NotificationType::ReportReady;
    }

    /**
     * Always in-app — this is the direct result of the user's own export request.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Not sent (see via()), but required by the base contract.
        return (new MailMessage)
            ->subject('Your report is ready')
            ->action('Download report', route('reports.download', $this->report));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your report is ready',
            'body' => $this->report->title.' · '.strtoupper($this->report->format),
            'url' => route('reports.download', $this->report),
        ];
    }
}
