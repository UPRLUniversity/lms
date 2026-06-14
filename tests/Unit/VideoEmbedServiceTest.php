<?php

namespace Tests\Unit;

use App\Services\Courses\VideoEmbedService;
use PHPUnit\Framework\TestCase;

class VideoEmbedServiceTest extends TestCase
{
    private VideoEmbedService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VideoEmbedService;
    }

    /**
     * @dataProvider youtubeUrls
     */
    public function test_it_parses_youtube_urls(string $url): void
    {
        $this->assertSame('youtube', $this->service->provider($url));
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $this->service->embedUrl($url));
    }

    public static function youtubeUrls(): array
    {
        return [
            ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            ['https://youtu.be/dQw4w9WgXcQ'],
            ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
            ['https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ'],
        ];
    }

    public function test_it_parses_vimeo_urls(): void
    {
        $this->assertSame('vimeo', $this->service->provider('https://vimeo.com/123456789'));
        $this->assertSame('https://player.vimeo.com/video/123456789', $this->service->embedUrl('https://vimeo.com/123456789'));
    }

    public function test_it_rejects_unrecognised_urls(): void
    {
        $this->assertNull($this->service->parse('https://example.com/video'));
        $this->assertFalse($this->service->isValid('not a url'));
        $this->assertNull($this->service->embedUrl('https://dailymotion.com/x123'));
    }
}
