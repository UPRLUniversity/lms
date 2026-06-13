<?php

namespace App\Models;

use App\Enums\MediaPurpose;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'purpose',
        'visibility',
        'provider',
        'disk',
        'path',
        'public_id',
        'url',
        'mime',
        'size_bytes',
        'width',
        'height',
        'original_name',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => MediaPurpose::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * The owning model (course, lesson, user, …).
     *
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }
}
