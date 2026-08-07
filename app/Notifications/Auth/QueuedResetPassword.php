<?php

namespace App\Notifications\Auth;

use App\Notifications\Concerns\RetriesTransientMailFailures;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued for the same reason as [[QueuedVerifyEmail]]: "forgot password" is
 * submitted by someone already locked out, and making them watch a multi-second
 * SMTP handshake — or a 500 — is the worst possible moment to block.
 *
 * The branded copy set by AppServiceProvider::brandedAuthMail() is inherited via
 * ResetPassword's static $toMailCallback, so the message itself is unchanged.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
    use RetriesTransientMailFailures;
}
