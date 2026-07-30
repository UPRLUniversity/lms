<?php

namespace App\Exceptions;

use Exception;

/**
 * A coupon was rejected. The message is written to be shown to the buyer verbatim, so
 * it must explain what is wrong without revealing anything about other people's usage
 * or the existence of codes they were not given.
 *
 * Note `unknown()` and `expired()` say different things deliberately: telling someone
 * a code exists but has expired is useful; telling them a code they guessed exists at
 * all is not, so an unknown code and an inactive one give the same answer.
 */
class CouponException extends Exception
{
    public static function unknown(): self
    {
        return new self('That code is not valid.');
    }

    public static function notStarted(): self
    {
        return new self('That code is not active yet.');
    }

    public static function expired(): self
    {
        return new self('That code has expired.');
    }

    public static function exhausted(): self
    {
        return new self('That code has reached its usage limit.');
    }

    public static function alreadyUsed(): self
    {
        return new self('You have already used that code.');
    }

    public static function notApplicable(): self
    {
        return new self('That code does not apply to anything in your cart.');
    }

    public static function emptyCart(): self
    {
        return new self('Add a course to your cart before applying a code.');
    }
}
