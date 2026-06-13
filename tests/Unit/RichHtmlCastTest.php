<?php

namespace Tests\Unit;

use App\Casts\RichHtml;
use App\Models\User;
use Tests\TestCase;

class RichHtmlCastTest extends TestCase
{
    private function clean(string $html, string $profile = 'rich'): string
    {
        $cast = new RichHtml($profile);

        return (string) $cast->set(new User(), 'body', $html, []);
    }

    public function test_strips_script_tags_and_contents(): void
    {
        $out = $this->clean('<p>Hello</p><script>alert(document.cookie)</script>');

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('alert(', $out);
        $this->assertStringContainsString('Hello', $out);
    }

    public function test_strips_event_handler_attributes(): void
    {
        $out = $this->clean('<p onclick="steal()" onmouseover="x()">Click</p>');

        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onmouseover', $out);
        $this->assertStringContainsString('Click', $out);
    }

    public function test_strips_javascript_url_in_links(): void
    {
        $out = $this->clean('<a href="javascript:alert(1)">x</a><a href="https://uprl.test">ok</a>');

        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringContainsString('https://uprl.test', $out);
    }

    public function test_strips_disallowed_tags_but_keeps_text(): void
    {
        $out = $this->clean('<iframe src="evil"></iframe><style>body{}</style><p>Safe</p>');

        $this->assertStringNotContainsString('<iframe', $out);
        $this->assertStringNotContainsString('<style', $out);
        $this->assertStringContainsString('Safe', $out);
    }

    public function test_preserves_allowed_rich_formatting(): void
    {
        $html = '<h2>Title</h2><p><strong>bold</strong> and <em>italic</em></p>'
            .'<ul><li>one</li></ul>'
            .'<a href="https://uprl.test" title="t">link</a>'
            .'<table><thead><tr><th>H</th></tr></thead><tbody><tr><td>C</td></tr></tbody></table>';

        $out = $this->clean($html, 'rich');

        foreach (['<h2', '<strong>', '<em>', '<ul>', '<li>', '<a href="https://uprl.test"', '<table>', '<th>', '<td>'] as $needle) {
            $this->assertStringContainsString($needle, $out, "expected to keep: {$needle}");
        }
    }

    public function test_basic_profile_drops_images_and_tables(): void
    {
        $html = '<p><strong>hi</strong></p><img src="x.png"><table><tr><td>c</td></tr></table>'
            .'<script>bad()</script>';

        $out = $this->clean($html, 'basic');

        $this->assertStringContainsString('<strong>hi</strong>', $out);
        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringNotContainsString('<table', $out);
        $this->assertStringNotContainsString('<script', $out);
    }
}
