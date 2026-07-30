<?php

namespace App\Support\Commerce;

use App\Enums\OrderItemKind;
use App\Models\Course;
use App\Models\Programme;
use App\Support\Money;

/**
 * One chargeable line, before it becomes either a cart row on screen or an OrderItem
 * in the database. Immutable: PricingService produces these, the cart renders them,
 * and CheckoutService turns them into order items — nothing mutates one in between.
 *
 * `title` is resolved here so the same wording reaches the cart, the checkout summary
 * and the persisted order, rather than each surface inventing its own.
 */
final class PriceLine
{
    private function __construct(
        public readonly OrderItemKind $kind,
        public readonly string $title,
        public readonly float $amount,
        public readonly ?Course $course = null,
        public readonly ?Programme $programme = null,
    ) {}

    public static function course(Course $course, float $amount): self
    {
        return new self(
            kind: OrderItemKind::Course,
            title: $course->title,
            amount: round($amount, 2),
            course: $course,
        );
    }

    public static function fee(OrderItemKind $kind, Programme $programme, float $amount): self
    {
        return new self(
            kind: $kind,
            title: "{$programme->name} — {$kind->label()}",
            amount: round($amount, 2),
            programme: $programme,
        );
    }

    public function isEntryFee(): bool
    {
        return $this->kind->isEntryFee();
    }

    public function isFree(): bool
    {
        return $this->amount <= 0;
    }

    public function formattedAmount(): string
    {
        return Money::formatOrFree($this->amount);
    }
}
