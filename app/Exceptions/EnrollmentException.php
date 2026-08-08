<?php

namespace App\Exceptions;

use App\Support\Courses\ProgressionVerdict;
use RuntimeException;

/**
 * A self-enrolment couldn't proceed for a reason worth telling the student about
 * (invitation-only, window closed, already enrolled). Carries a ready-to-show,
 * human message; controllers catch it and flash it as an error toast.
 */
class EnrollmentException extends RuntimeException
{
    /**
     * Set only by prerequisiteNotMet(), so a caller that wants more than the sentence
     * (the blocking part, to link to it) can reach it without re-running the check.
     */
    public ?ProgressionVerdict $verdict = null;

    public static function inviteOnly(): self
    {
        return new self('This course is enrolment by invitation only.');
    }

    public static function windowClosed(): self
    {
        return new self('Enrolment for this course is closed.');
    }

    public static function windowNotOpen(): self
    {
        return new self('Enrolment for this course has not opened yet.');
    }

    public static function notPublished(): self
    {
        return new self('This course is not open for enrolment.');
    }

    public static function alreadyEnrolled(): self
    {
        return new self('You are already enrolled in this course.');
    }

    /**
     * A paid course cannot be self-enrolled onto without a paid order. Raised by
     * EnrollmentService::selfEnroll — the single paywall in the application.
     */
    public static function paymentRequired(): self
    {
        return new self('This course must be purchased before you can enrol.');
    }

    /**
     * An earlier part of the programme has not been cleared yet.
     *
     * The message is the VERDICT's, never a fresh sentence written here — the course
     * page, the catalogue chip, the cart and this exception must all say the same thing,
     * or a student ends up believing they need something they do not.
     */
    public static function prerequisiteNotMet(ProgressionVerdict $verdict): self
    {
        $exception = new self((string) $verdict->message());
        $exception->verdict = $verdict;

        return $exception;
    }
}
