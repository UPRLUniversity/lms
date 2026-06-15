<?php

namespace App\Models;

use App\Enums\MediaPurpose;
use App\Models\Concerns\HasMedia;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasMedia, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'title',
        'bio',
        'learning_preferences',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'learning_preferences' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Only active accounts may use the application; deactivation is the no-hard-delete
     * alternative to removing a user.
     *
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * This user's enrollments across courses (the basis of the "My Learning" page).
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Two-letter initials for the avatar fallback (e.g. "Ada Lovelace" → "AL").
     */
    public function initials(): string
    {
        $initials = Str::of($this->name ?? '')
            ->explode(' ')
            ->filter()
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->implode('');

        return $initials !== '' ? $initials : 'U';
    }

    /**
     * The current avatar Media record, if one has been uploaded.
     */
    public function avatar(): ?Media
    {
        return $this->firstMediaFor(MediaPurpose::Avatars);
    }

    /**
     * Public URL for the avatar, or null when the initials fallback should show.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar()?->url;
    }

    /**
     * Whether the user has opted in to the periodic e-mail digest.
     */
    public function wantsEmailDigest(): bool
    {
        return (bool) ($this->learning_preferences['email_digest'] ?? false);
    }
}
