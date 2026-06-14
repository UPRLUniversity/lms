<?php

namespace App\Enums;

/**
 * Every distinct kind of file the LMS stores. Each case maps, in config/media.php,
 * to a visibility, disk, allowed mime types, max size and (for public images)
 * Cloudinary transformation hints — so a purpose's backend can change without
 * touching code.
 */
enum MediaPurpose: string
{
    case Avatars = 'avatars';
    case CourseCovers = 'course_covers';
    case LessonImages = 'lesson_images';
    case EditorUploads = 'editor_uploads';
    case LessonResources = 'lesson_resources';
    case Submissions = 'submissions';
    case Certificates = 'certificates';
    case Signatures = 'signatures';

    /**
     * The full config block for this purpose.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return config("media.purposes.{$this->value}", []);
    }

    public function visibility(): string
    {
        return $this->config()['visibility'] ?? 'private';
    }

    public function isPublic(): bool
    {
        return $this->visibility() === 'public';
    }

    /**
     * Filesystem disk this purpose is stored on.
     */
    public function disk(): string
    {
        return $this->config()['disk'] ?? ($this->isPublic() ? 'public' : 'private');
    }

    /**
     * Allowed MIME types for this purpose.
     *
     * @return array<int, string>
     */
    public function allowedMimes(): array
    {
        return $this->config()['allowed_mimes'] ?? [];
    }

    /**
     * Maximum accepted size in kilobytes.
     */
    public function maxKb(): int
    {
        return (int) ($this->config()['max_kb'] ?? 0);
    }

    /**
     * Cloudinary transformation hints (public images only).
     *
     * @return array<string, mixed>
     */
    public function transformations(): array
    {
        return $this->config()['transformations'] ?? [];
    }
}
