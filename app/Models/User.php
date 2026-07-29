<?php

namespace App\Models;

use App\Enums\MediaPurpose;
use App\Enums\NotificationType;
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
     * Certificates issued to this user (Section 7) — surfaced on the student
     * dashboard's achievements strip and the reporting layer.
     *
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * This user's per-lesson progress rows (the basis of the learning player and
     * history). Keyed work happens in LearningService.
     *
     * @return HasMany<LessonProgress, $this>
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Conversations (direct + group) this user takes part in (Section 9). last_read_at
     * on the pivot is the read watermark used to derive unread counts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Conversation, $this>
     */
    public function conversations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
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
     * Whether the user has opted in to the periodic e-mail digest. Section 8 gives
     * this existing toggle teeth: when on, digestible notification types are
     * withheld from immediate e-mail and folded into the daily digest instead.
     */
    public function wantsEmailDigest(): bool
    {
        return (bool) ($this->learning_preferences['email_digest'] ?? false);
    }

    /**
     * Whether $type should reach this user's in-app bell. Critical types (status
     * changes the user must not miss) are always on, regardless of the saved
     * preference — the profile toggle for them renders locked/checked.
     */
    public function notifiesInApp(NotificationType $type): bool
    {
        if ($type->isCritical()) {
            return true;
        }

        return (bool) data_get($this->learning_preferences, "notifications.{$type->value}.in_app", true);
    }

    /**
     * Whether $type should reach this user's inbox at all (immediately, or folded
     * into their daily digest if they've opted into one and the type qualifies).
     */
    public function notifiesByEmail(NotificationType $type): bool
    {
        return (bool) data_get($this->learning_preferences, "notifications.{$type->value}.email", true);
    }

    /**
     * Persist one type's channel toggles, merging into the existing preference
     * blob so unrelated keys (other types, email_digest) are never clobbered.
     *
     * @param  array{email?: bool, in_app?: bool}  $channels
     */
    public function setNotificationPreference(NotificationType $type, array $channels): void
    {
        $prefs = $this->learning_preferences ?? [];
        $prefs['notifications'][$type->value] = array_merge(
            $prefs['notifications'][$type->value] ?? [],
            $channels,
        );

        $this->learning_preferences = $prefs;
    }
}
