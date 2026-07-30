<?php

namespace App\Support\Payments;

/**
 * What the app should do next to collect payment for an order.
 *
 * Three shapes, because the three drivers genuinely differ: a hosted gateway sends
 * the buyer away (redirect), an offline method shows them instructions and waits
 * (instruct), and a zero-total or sandbox order is simply done (settled).
 */
final class PaymentIntent
{
    private function __construct(
        public readonly string $mode,          // redirect | instruct | settled
        public readonly ?string $redirectUrl = null,
        public readonly ?string $reference = null,
        public readonly ?string $message = null,
    ) {}

    /** Send the buyer to the gateway's hosted page. */
    public static function redirect(string $url, ?string $reference = null): self
    {
        return new self(mode: 'redirect', redirectUrl: $url, reference: $reference);
    }

    /** Show the buyer what to do, and leave the order awaiting payment. */
    public static function instruct(?string $message = null): self
    {
        return new self(mode: 'instruct', message: $message);
    }

    /** Nothing to collect — the order is already settled. */
    public static function settled(?string $reference = null): self
    {
        return new self(mode: 'settled', reference: $reference);
    }

    public function isRedirect(): bool
    {
        return $this->mode === 'redirect';
    }

    public function isSettled(): bool
    {
        return $this->mode === 'settled';
    }

    public function isInstruction(): bool
    {
        return $this->mode === 'instruct';
    }
}
