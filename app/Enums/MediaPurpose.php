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
    case ProgrammeCovers = 'programme_covers';
    case LessonImages = 'lesson_images';
    case QuestionImages = 'question_images';
    case EditorUploads = 'editor_uploads';
    case LessonMedia = 'lesson_media';
    case LessonResources = 'lesson_resources';
    case AssignmentResources = 'assignment_resources';
    case Submissions = 'submissions';
    case MessageAttachments = 'message_attachments';
    case Certificates = 'certificates';
    case Signatures = 'signatures';

    /**
     * Institution artwork uploaded from /admin/settings — the logo variants and the
     * favicon. Public, like any other brand image, and resolved everywhere through
     * App\Services\Branding\BrandAssets.
     */
    case BrandAssets = 'brand_assets';

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
     * Allowed MIME types for this purpose — the purpose's own allow-list narrowed
     * by the administrator's general list (config/media.php → limits), where one
     * governs this purpose.
     *
     * Intersection, never union: an administrator tightening what the institution
     * accepts must be able to remove a type, and must never be able to add one a
     * purpose deliberately excludes.
     *
     * @return array<int, string>
     */
    public function allowedMimes(): array
    {
        $own = $this->config()['allowed_mimes'] ?? [];
        $ceiling = $this->mimeCeiling();

        if ($own === [] || $ceiling === []) {
            return $own;
        }

        $narrowed = array_values(array_intersect($own, $ceiling));

        // An administrator who narrows the list to nothing this purpose accepts has
        // made a mistake, not a policy: fall back to the purpose's own list rather
        // than silently rejecting every upload to it.
        return $narrowed === [] ? $own : $narrowed;
    }

    /**
     * Maximum accepted size in kilobytes — the stricter of the purpose's own
     * ceiling and the administrator's general one.
     */
    public function maxKb(): int
    {
        $own = (int) ($this->config()['max_kb'] ?? 0);
        $ceiling = $this->sizeCeiling();

        if ($own <= 0) {
            return max($ceiling, 0);
        }

        return $ceiling > 0 ? min($own, $ceiling) : $own;
    }

    /**
     * The administrator-set size ceiling governing this purpose, or 0 for the
     * purposes deliberately left ungoverned (see config/media.php → limits).
     */
    private function sizeCeiling(): int
    {
        return match (true) {
            $this->governedBy('image') => (int) config('media.limits.image_kb', 0),
            $this->governedBy('document') => (int) config('media.limits.document_kb', 0),
            default => 0,
        };
    }

    /**
     * @return array<int, string>
     */
    private function mimeCeiling(): array
    {
        $list = match (true) {
            $this->governedBy('image') => (string) config('media.limits.image_mimes', ''),
            $this->governedBy('document') => (string) config('media.limits.document_mimes', ''),
            default => '',
        };

        if (trim($list) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $list))));
    }

    private function governedBy(string $family): bool
    {
        return in_array($this->value, config("media.limits.{$family}_purposes", []), true);
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
