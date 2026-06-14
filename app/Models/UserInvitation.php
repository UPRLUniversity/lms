<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;

/**
 * An admin-issued invitation to join the LMS. The raw token only ever exists in
 * the e-mailed link; the column stores its hash. Status is derived, never stored.
 */
class UserInvitation extends Model
{
    /** @use HasFactory<UserInvitationFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return ! $this->isAccepted() && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * pending | accepted | expired — for the admin list and badges.
     */
    public function status(): string
    {
        return match (true) {
            $this->isAccepted() => 'accepted',
            $this->isExpired() => 'expired',
            default => 'pending',
        };
    }
}
