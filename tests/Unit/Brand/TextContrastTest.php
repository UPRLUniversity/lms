<?php

namespace Tests\Unit\Brand;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Faded text must stay legible.
 *
 * The brand system fades text with an opacity on --uprl-ink, and below a certain step that
 * stops being "quiet" and becomes unreadable. Measured against both light backgrounds the
 * app uses:
 *
 *   opacity   on #FFFFFF   on #FAF9F6   WCAG AA (4.5:1)
 *      /50        3.35         3.31     fail
 *      /55        3.93         3.84     fail
 *      /60        4.58         4.49     fail — passes on white, fails on the app background
 *      /65        5.40         5.33     PASS   ← the floor
 *
 * White text on crimson behaves the same way: /80 measures 4.13:1 on #C8102E, /85 measures
 * 4.53:1, so /85 is the floor there.
 *
 * A grep test rather than a rendered one on purpose: this is a rule about the source, it
 * runs in milliseconds, and it catches the mistake at the moment somebody types it rather
 * than after a Lighthouse run somebody has to remember to do.
 */
class TextContrastTest extends TestCase
{
    /** Utilities that colour TEXT. Backgrounds, borders and rings are unaffected. */
    private const INK_TOO_FAINT = '/text-ink\/(3[0-9]|4[0-9]|5[0-9]|6[0-4])\b/';

    // 11–84. /85 and above pass; /10 and below is only ever the decorative sunburst, which
    // is aria-hidden ornament rather than text.
    private const WHITE_TOO_FAINT = '/text-white\/(1[1-9]|[2-7][0-9]|8[0-4])\b/';

    public function test_no_ink_text_is_fainter_than_the_legible_floor(): void
    {
        $offenders = $this->scan(self::INK_TOO_FAINT);

        $this->assertSame([], $offenders, "Text below text-ink/65 fails WCAG AA on the app's own background.\n".implode("\n", $offenders));
    }

    public function test_no_white_text_on_a_coloured_surface_is_fainter_than_the_floor(): void
    {
        $offenders = $this->scan(self::WHITE_TOO_FAINT);

        $this->assertSame([], $offenders, "White text below text-white/85 fails WCAG AA on crimson.\n".implode("\n", $offenders));
    }

    /**
     * @return array<int, string>
     */
    private function scan(string $pattern): array
    {
        $root = dirname(__DIR__, 3).'/resources/views';
        $offenders = [];

        /** @var \SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $i => $line) {
                if (preg_match($pattern, $line, $m)) {
                    $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $offenders[] = "  {$relative}:".($i + 1)." — {$m[0]}";
                }
            }
        }

        return $offenders;
    }
}
