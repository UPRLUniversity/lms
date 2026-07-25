<?php

namespace App\Models;

use App\Casts\RichHtml;
use Database\Factories\ForumPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reply within a forum thread. One level of nesting: a reply either sits at the top
 * level (parent_id null) or replies to a top-level post (never to another reply — the
 * service enforces it). Soft-deleted so moderation hides a post without erasing the
 * conversation around it.
 */
class ForumPost extends Model
{
    /** @use HasFactory<ForumPostFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'forum_thread_id',
        'user_id',
        'parent_id',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'body' => RichHtml::class.':basic',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<ForumThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<ForumPost, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'parent_id');
    }

    /**
     * Direct replies to this post, oldest first.
     *
     * @return HasMany<ForumPost, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * @return HasMany<ForumPostReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ForumPostReport::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Whether this post is the accepted answer of its thread.
     */
    public function isAnswer(): bool
    {
        return $this->thread?->answer_post_id === $this->id;
    }
}
