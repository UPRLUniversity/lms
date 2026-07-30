<?php

namespace App\Enums;

/**
 * Lifecycle of an order.
 *
 * Pending is the moment between "checkout submitted" and "gateway contacted".
 * AwaitingPayment is the offline path — a bank transfer sits here until an admin
 * confirms the money arrived. Only Paid grants course access, and only ever through
 * OrderFulfilmentService.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingPayment => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Variant passed to <x-ui.badge>.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::AwaitingPayment, self::Pending => 'gold',
            self::Failed, self::Cancelled, self::Refunded => 'neutral',
        };
    }

    /**
     * Whether this order currently entitles the buyer to what it contains. Refunded
     * deliberately does NOT — access is withdrawn with the money.
     */
    public function grantsAccess(): bool
    {
        return $this === self::Paid;
    }

    /**
     * Whether the order is still waiting on the buyer or the gateway, and so should
     * be resumable from their order list.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::AwaitingPayment;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
