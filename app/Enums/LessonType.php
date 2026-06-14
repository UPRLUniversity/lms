<?php

namespace App\Enums;

/**
 * The kind of content a lesson delivers. Each type drives which content payload is
 * collected in the builder, which icon is shown, and how the lesson renders.
 *
 *   video         → an embedded YouTube/Vimeo URL, or an uploaded mp4
 *   text          → rich HTML authored in the editor
 *   pdf           → an uploaded PDF (private file)
 *   document      → an uploaded office document (private file)
 *   audio         → an uploaded audio file (private file)
 *   external_link → a link out to another resource
 */
enum LessonType: string
{
    case Video = 'video';
    case Text = 'text';
    case Pdf = 'pdf';
    case Document = 'document';
    case Audio = 'audio';
    case ExternalLink = 'external_link';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Text => 'Text / reading',
            self::Pdf => 'PDF',
            self::Document => 'Document',
            self::Audio => 'Audio',
            self::ExternalLink => 'External link',
        };
    }

    /**
     * Icon name resolved by <x-ui.icon>.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Video => 'play',
            self::Text => 'document-text',
            self::Pdf => 'document',
            self::Document => 'document',
            self::Audio => 'audio',
            self::ExternalLink => 'link',
        };
    }

    /**
     * Whether this type stores its payload as an uploaded file (private disk).
     */
    public function isFileUpload(): bool
    {
        return in_array($this, [self::Pdf, self::Document, self::Audio], true);
    }

    public function isVideo(): bool
    {
        return $this === self::Video;
    }

    public function isText(): bool
    {
        return $this === self::Text;
    }

    public function isExternalLink(): bool
    {
        return $this === self::ExternalLink;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
