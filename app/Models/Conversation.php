<?php

namespace App\Models;

use App\Enums\ConversationType;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * A private thread between people: a one-to-one direct conversation (reused if it
 * already exists) or a named group (an instructor messaging a course's students).
 * Membership is the access gate — only participants see or post. Each participant
 * carries a last_read_at watermark on the pivot, from which unread counts are derived.
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'subject',
        'course_id',
        'created_by',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'last_message_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * Messages, oldest first (reading order).
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    /**
     * The newest message — eager-loadable for the inbox preview line.
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Conversations a given user takes part in.
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeForParticipant(Builder $query, User $user): void
    {
        $query->whereHas('participants', fn (Builder $q) => $q->whereKey($user->id));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isDirect(): bool
    {
        return $this->type === ConversationType::Direct;
    }

    public function isGroup(): bool
    {
        return $this->type === ConversationType::Group;
    }

    public function hasParticipant(User $user): bool
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->contains('id', $user->id);
        }

        return $this->participants()->whereKey($user->id)->exists();
    }

    /**
     * The other person in a direct conversation (the one who isn't $user).
     */
    public function otherParticipant(User $user): ?User
    {
        return $this->participants->firstWhere('id', '!=', $user->id);
    }

    /**
     * A display title from $user's point of view: a group's subject, or the other
     * person's name for a direct thread.
     */
    public function titleFor(User $user): string
    {
        if ($this->isGroup()) {
            return $this->subject ?: 'Group conversation';
        }

        return $this->otherParticipant($user)?->name ?? 'Conversation';
    }

    /**
     * How many messages in this conversation are unread for $user — messages from
     * other people posted after their last_read_at watermark. Your own messages never
     * count as unread. Relies on the participants relation being loaded.
     */
    public function unreadCountFor(User $user): int
    {
        $participant = $this->participants->firstWhere('id', $user->id);

        if (! $participant) {
            return 0;
        }

        $since = $participant->pivot->last_read_at;

        return $this->messages
            ->where('user_id', '!=', $user->id)
            ->when($since !== null, fn (Collection $m) => $m->filter(fn (Message $msg) => $msg->created_at->greaterThan($since)))
            ->count();
    }
}
