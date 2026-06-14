<?php

namespace App\Models;

use App\Casts\RichHtml;
use App\Enums\LessonType;
use App\Enums\MediaPurpose;
use App\Models\Concerns\HasMedia;
use App\Services\Courses\VideoEmbedService;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, HasMedia;

    protected $fillable = [
        'module_id',
        'title',
        'type',
        'content_text',
        'video_url',
        'video_provider',
        'external_url',
        'duration_minutes',
        'is_free_preview',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'type' => LessonType::class,
            'content_text' => RichHtml::class,
            'duration_minutes' => 'integer',
            'is_free_preview' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * The primary uploaded file for a file-type lesson (PDF/document/audio) or an
     * uploaded lesson video — stored privately via PrivateFileService.
     */
    public function file(): ?Media
    {
        return $this->firstMediaFor(MediaPurpose::LessonMedia);
    }

    /**
     * Extra downloadable resources attached to the lesson.
     *
     * @return Collection<int, Media>
     */
    public function resources(): Collection
    {
        return $this->mediaFor(MediaPurpose::LessonResources);
    }

    public function isUploadedVideo(): bool
    {
        return $this->type === LessonType::Video && $this->video_provider === 'upload';
    }

    /**
     * The embeddable iframe URL for an embedded (YouTube/Vimeo) video lesson, or
     * null when the lesson isn't an embed.
     */
    public function videoEmbedUrl(): ?string
    {
        if ($this->type !== LessonType::Video || $this->isUploadedVideo() || ! $this->video_url) {
            return null;
        }

        return app(VideoEmbedService::class)->embedUrl($this->video_url);
    }
}
