<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A self-enrolment couldn't proceed for a reason worth telling the student about
 * (invitation-only, window closed, already enrolled). Carries a ready-to-show,
 * human message; controllers catch it and flash it as an error toast.
 */
class EnrollmentException extends RuntimeException
{
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
}
