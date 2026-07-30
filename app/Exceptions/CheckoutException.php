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
}
