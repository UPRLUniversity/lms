<?php

namespace App\Notifications\Auth;

use App\Notifications\Concerns\RetriesTransientMailFailures;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The framework's VerifyEmail is not queued, so registration paid for the SMTP
 * round-trip inline — seconds of dead time on the one request a new learner
 * judges us by, and a 500 (after the account row was already written) whenever
 * the mail host hiccuped. Queueing it is the constitution's rule for every other
 * notification in the app; this closes the last exception.
 *
 * Subclassing rather than re-implementing keeps the branded copy registered by
 * AppServiceProvider::brandedAuthMail() — VerifyEmail::$toMailCallback is a static
 * this class inherits, so the crimson-themed message is unchanged.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;
    use RetriesTransientMailFailures;
}
