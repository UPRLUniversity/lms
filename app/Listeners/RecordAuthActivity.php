<?php

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Authentication events into the audit trail.
 *
 * A failed sign-in records its ORIGIN — address, user agent, and the address that
 * was tried — because that is the whole value of logging failures: a burst of them
 * against one account, or a spread of accounts from one address, is the signature
 * worth catching. It deliberately records the attempted e-mail and never the
 * attempted password, which is not stored, hashed or otherwise.
 *
 * A failure has no authenticated causer by definition, so these entries are
 * attributed to the targeted user when the address matches a real account and to
 * nobody when it does not — the latter being exactly what someone probing for
 * valid addresses produces.
 */
class RecordAuthActivity
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Request $request,
    ) {}

    public function handleLogin(Login $event): void
    {
        $this->audit->record(
            AuditEvent::LoginSucceeded,
            $event->user instanceof User ? $event->user : null,
            $this->origin(),
            'Signed in',
        );
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->audit->record(AuditEvent::LoggedOut, $event->user, $this->origin(), 'Signed out');
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        // Attribute to the real account when the address exists, so "who is being
        // targeted" is answerable from the subject column alone.
        $subject = $event->user instanceof User
            ? $event->user
            : ($email ? User::where('email', $email)->first() : null);

        $this->audit->record(
            AuditEvent::LoginFailed,
            $subject,
            $this->origin() + [
                'attempted_email' => $email,
                'account_exists' => $subject !== null,
            ],
            'Failed sign-in attempt',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function origin(): array
    {
        return [
            'ip' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
        ];
    }
}
