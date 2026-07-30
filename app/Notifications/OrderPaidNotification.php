<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The buyer's receipt, sent once an order reaches Paid. Marked critical in
 * NotificationType because it is a financial record — a student must not be able to
 * switch off proof of what they were charged.
 */
class OrderPaidNotification extends UprlNotification
{
    public function __construct(public Order $order) {}

    public static function type(): NotificationType
    {
        return NotificationType::OrderPaid;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing('items');
        $courses = $this->order->courseItems();

        $mail = (new MailMessage)
            ->subject('Payment received — '.$this->order->shortReference())
            ->greeting("Thank you, {$notifiable->name}.")
            ->line('We have received your payment of '.$this->order->formattedTotal().'.')
            ->line('Reference: '.$this->order->shortReference());

        if ($courses->isNotEmpty()) {
            $mail->line($courses->count() === 1 ? 'You now have access to:' : 'You now have access to:');

            foreach ($courses as $item) {
                $mail->line('• '.$item->title);
            }
        }

        return $mail
            ->action('View your receipt', route('orders.show', $this->order))
            ->salutation(config('brand.motto'));
    }

    public function toArray(object $notifiable): array
    {
        $count = $this->order->courseItems()->count();

        return [
            'title' => 'Payment received',
            'body' => $this->order->formattedTotal().' paid — '
                .($count === 1 ? '1 course is' : "{$count} courses are").' now available.',
            'url' => route('orders.show', $this->order),
        ];
    }
}
