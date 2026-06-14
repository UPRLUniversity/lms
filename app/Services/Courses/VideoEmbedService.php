<?php

namespace App\Services\Courses;

/**
 * Parses YouTube / Vimeo URLs into a normalised provider + video id, and builds the
 * privacy-friendly embed URL used in the builder preview and on the lesson page.
 * The single source of truth for video parsing — controllers and the Lesson model
 * both defer to it, never re-implementing the regexes.
 */
class VideoEmbedService
{
    /**
     * Resolve a pasted URL to ['provider' => 'youtube'|'vimeo', 'id' => '...'] or
     * null when it isn't a recognised YouTube/Vimeo link.
     *
     * @return array{provider: string, id: string}|null
     */
    public function parse(string $url): ?array
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if ($id = $this->youtubeId($url)) {
            return ['provider' => 'youtube', 'id' => $id];
        }

        if ($id = $this->vimeoId($url)) {
            return ['provider' => 'vimeo', 'id' => $id];
        }

        return null;
    }

    public function isValid(string $url): bool
    {
        return $this->parse($url) !== null;
    }

    public function provider(string $url): ?string
    {
        return $this->parse($url)['provider'] ?? null;
    }

    /**
     * The iframe src for a recognised URL, or null if it can't be parsed.
     */
    public function embedUrl(string $url): ?string
    {
        $parsed = $this->parse($url);

        if ($parsed === null) {
            return null;
        }

        return match ($parsed['provider']) {
            'youtube' => 'https://www.youtube-nocookie.com/embed/'.$parsed['id'],
            'vimeo' => 'https://player.vimeo.com/video/'.$parsed['id'],
            default => null,
        };
    }

    private function youtubeId(string $url): ?string
    {
        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/watch\?[^ ]*v=([A-Za-z0-9_-]{11})~',
            '~youtube\.com/embed/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~',
            '~youtube-nocookie\.com/embed/([A-Za-z0-9_-]{11})~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function vimeoId(string $url): ?string
    {
        if (preg_match('~vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d{6,})~', $url, $m)) {
            return $m[1];
        }

        if (preg_match('~player\.vimeo\.com/video/(\d{6,})~', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
