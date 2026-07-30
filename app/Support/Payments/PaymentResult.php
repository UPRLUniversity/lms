<?php

namespace App\Support\Payments;

/**
 * The outcome of verifying a payment, whether that verification came from a callback
 * or a webhook.
 *
 * `pending` is distinct from `failed` on purpose: an unfinished bank transfer or a
 * gateway still processing must not mark an order failed, because a later success is
 * still possible and marking it failed would strand a buyer who did in fact pay.
 */
final class PaymentResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly string $status,        // paid | failed | pending | ignored
        public readonly ?string $reference = null,
        public readonly ?string $orderReference = null,
        public readonly array $payload = [],
        public readonly ?string $message = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function paid(?string $reference = null, ?string $orderReference = null, array $payload = []): self
    {
        return new self(status: 'paid', reference: $reference, orderReference: $orderReference, payload: $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function failed(?string $message = null, ?string $orderReference = null, array $payload = []): self
    {
        return new self(status: 'failed', orderReference: $orderReference, payload: $payload, message: $message);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function pending(?string $orderReference = null, array $payload = []): self
    {
        return new self(status: 'pending', orderReference: $orderReference, payload: $payload);
    }

    /**
     * An event we understood but do not act on — e.g. a gateway sending us a
     * subscription event when we only sell one-off purchases. Acknowledged with 200 so
     * the gateway stops retrying, but nothing changes.
     */
    public static function ignored(?string $message = null): self
    {
        return new self(status: 'ignored', message: $message);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
