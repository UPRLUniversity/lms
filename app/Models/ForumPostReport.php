<?php

namespace App\Models;

use Database\Factories\ForumPostReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's flag on a forum post for admin review. This is only the queue entry;
 * actually removing the post is a separate moderation action. One flag per user per
 * post (DB unique), so re-reporting updates the same row.
 */
class ForumPostReport extends Model
{
    /** @use HasFactory<ForumPostReportFactory> */
    use HasFactory;

    protected $fillable = [
        'forum_post_id',
        'user_id',
        'reason',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ForumPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'forum_post_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Reports still awaiting an admin's attention.
     *
     * @param  Builder<ForumPostReport>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
