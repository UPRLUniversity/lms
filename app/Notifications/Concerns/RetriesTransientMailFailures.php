<?php

namespace App\Notifications\Concerns;

/**
 * Retry policy shared by every queued notification.
 *
 * The mail host is shared and periodically stalls mid-handshake — the TCP connect
 * succeeds and then the SMTP greeting never arrives, so the send dies on a read
 * timeout. That is transient: the same message sends fine a minute later. Without
 * a retry the first stall silently drops a verification link or a reset link, which
 * is indistinguishable to the recipient from the original "no email ever arrives"
 * bug this all started as.
 *
 * Declared on the notifications rather than passed as `queue:work --tries` so the
 * guarantee survives whatever command the operator actually starts the worker with.
 */
trait RetriesTransientMailFailures
{
    public int $tries = 3;

    /**
     * Widening backoff: a stall usually clears within a minute, and anything still
     * failing five minutes later is an outage worth surfacing in failed_jobs.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300];
}
