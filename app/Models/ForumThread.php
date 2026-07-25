<?php

namespace App\Models;

use App\Casts\RichHtml;
use Database\Factories\ForumThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A discussion thread in a course's forum. Opened by any course member (an enrolled
 * student or an instructor); an optional lesson link scopes it to "Discuss this
 * lesson" from the Section-4 player. Moderation (pin/lock/remove) is instructor-only;
 * "mark as answer" points answer_post_id at the accepted reply, which flags the
 * thread Answered.
 */
class ForumThread extends Model
{
    /** @use HasFactory<ForumThreadFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id',
        'user_id',
        'lesson_id',
        'title',
        'body',
        'is_pinned',
        'is_locked',
        'answer_post_id',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'body' => RichHtml::class.':basic',
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Replies, oldest first (reading order).
     *
     * @return HasMany<ForumPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class)->orderBy('created_at');
    }

    /**
     * The accepted answer, if the thread has been resolved.
     *
     * @return BelongsTo<ForumPost, $this>
     */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'answer_post_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * The forum's default order: pinned first, then most-recently-active.
     *
     * @param  Builder<ForumThread>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_pinned')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');
    }

    /**
     * Threads with no accepted answer yet — the "unanswered" filter.
     *
     * @param  Builder<ForumThread>  $query
     */
    public function scopeUnanswered(Builder $query): void
    {
        $query->whereNull('answer_post_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isAnswered(): bool
    {
        return $this->answer_post_id !== null;
    }

    public function isPinned(): bool
    {
        return (bool) $this->is_pinned;
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    /**
     * Reply count, preferring a withCount-loaded value to avoid a per-row query.
     */
    public function replyCount(): int
    {
        return (int) ($this->posts_count ?? $this->posts()->count());
    }
}
