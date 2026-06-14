<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues, re-sends and accepts admin invitations. The raw token is returned to the
 * caller exactly once (to build the signed link) and is otherwise only stored as a
 * hash, so it can never be read back out of the database.
 */
class InvitationService
{
    public const TOKEN_BYTES = 32;

    public const EXPIRES_DAYS = 7;

    /**
     * Create an invitation and queue its e-mail. Returns the persisted record.
     */
    public function invite(string $name, string $email, Role $role, ?User $inviter = null): UserInvitation
    {
        $raw = $this->newRawToken();

        $invitation = UserInvitation::create([
            'name' => $name,
            'email' => $email,
            'role' => $role->value,
            'token' => $this->hash($raw),
            'invited_by' => $inviter?->id,
            'expires_at' => now()->addDays(self::EXPIRES_DAYS),
        ]);

        $this->notify($invitation, $raw);

        return $invitation;
    }

    /**
     * Rotate the token + expiry on a still-pending invitation and re-send it.
     */
    public function resend(UserInvitation $invitation): UserInvitation
    {
        $raw = $this->newRawToken();

        $invitation->update([
            'token' => $this->hash($raw),
            'expires_at' => now()->addDays(self::EXPIRES_DAYS),
        ]);

        $this->notify($invitation, $raw);

        return $invitation;
    }

    /**
     * Look up a pending invitation by id + raw token, constant-time and
     * expiry-aware. Returns null for any mismatch, reuse or expiry.
     */
    public function resolve(int $id, string $rawToken): ?UserInvitation
    {
        $invitation = UserInvitation::find($id);

        if (! $invitation || ! $invitation->isPending()) {
            return null;
        }

        if (! hash_equals($invitation->token, $this->hash($rawToken))) {
            return null;
        }

        return $invitation;
    }

    /**
     * Convert a valid invitation into a real, verified user with the granted role.
     * Wrapped in a transaction so a half-accepted invitation can't exist.
     */
    public function accept(UserInvitation $invitation, string $password): User
    {
        return DB::transaction(function () use ($invitation, $password) {
            $user = User::create([
                'name' => $invitation->name,
                'email' => $invitation->email,
                'password' => $password,            // hashed by the model cast
            ]);

            // Following the e-mailed link proves the address (email_verified_at is
            // guarded, so set it via the model API, not mass assignment).
            $user->markEmailAsVerified();

            $user->assignRole($invitation->role->value);

            $invitation->forceFill([
                'accepted_at' => now(),
                'user_id' => $user->id,
            ])->save();

            return $user;
        });
    }

    protected function notify(UserInvitation $invitation, string $rawToken): void
    {
        $invitation->notify(new UserInvitationNotification($invitation, $rawToken));
    }

    protected function newRawToken(): string
    {
        return Str::random(self::TOKEN_BYTES * 2);
    }

    protected function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
