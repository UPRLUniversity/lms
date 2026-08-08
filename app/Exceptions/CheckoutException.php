<?php

namespace App\Exceptions;

use Exception;

/**
 * Checkout could not proceed. Messages are shown to the buyer verbatim.
 */
class CheckoutException extends Exception
{
    public static function emptyCart(): self
    {
        return new self('Your cart is empty.');
    }

    public static function noPaymentMethod(): self
    {
        return new self('No payment method is available right now. Please contact us.');
    }

    public static function unavailableMethod(): self
    {
        return new self('That payment method is not available.');
    }

    public static function gatewayFailed(string $detail = ''): self
    {
        return new self(trim('We could not reach the payment provider. Please try again. '.$detail));
    }

    /**
     * A course in the cart sits behind a programme part the buyer has not cleared. Names
     * the course, because a cart holding several makes "you cannot buy this yet"
     * unactionable, and carries the verdict's own sentence for the reason.
     */
    public static function prerequisiteNotMet(string $courseTitle, string $reason): self
    {
        return new self("“{$courseTitle}” cannot be bought yet. {$reason} Remove it to check out with the rest.");
    }
}
