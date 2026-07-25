<?php

namespace App\Models;

use App\Casts\RichHtml;
use App\Enums\MediaPurpose;
use App\Models\Concerns\HasMedia;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single message inside a conversation. Body is sanitized on save (the 'basic'
 * profile — text formatting only, no images/tables). An optional attachment hangs off
 * the polymorphic media table via HasMedia (MediaPurpose::MessageAttachments), stored
 * privately and reachable only through the policy-gated download route.
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory, HasMedia;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'body' => RichHtml::class.':basic',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The single attachment on this message, if one was included. Prefers an
     * eager-loaded media relation (the conversation view loads it) to avoid an N+1.
     */
    public function attachment(): ?Media
    {
        if ($this->relationLoaded('media')) {
            return $this->media->firstWhere('purpose', MediaPurpose::MessageAttachments);
        }

        return $this->firstMediaFor(MediaPurpose::MessageAttachments);
    }
}
