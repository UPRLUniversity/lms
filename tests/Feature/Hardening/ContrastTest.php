<?php

namespace Tests\Feature\Hardening;

use Tests\TestCase;

/**
 * Contrast audit of the brand palette (Section 15 hardening sweep).
 *
 * Computed, not eyeballed. Every pairing the app actually uses is checked against
 * WCAG 2.1 AA — 4.5:1 for body text, 3:1 for large text (18.66px bold / 24px regular)
 * and for the non-text boundaries of interactive components such as a focus ring.
 *
 * These are the values the design system committed to in CLAUDE.md; the point of
 * asserting them is that a future palette tweak cannot quietly drop a pairing below AA.
 */
class ContrastTest extends TestCase
{
    private const CRIMSON = '#C8102E';

    private const CRIMSON_DARK = '#9E0B22';

    private const INK = '#1C1917';

    private const SURFACE = '#FAF9F6';

    private const CARD = '#FFFFFF';

    private const GREEN = '#0F6B3E';

    private const GOLD = '#C9A227';

    private const GOLD_INK = '#8A6A12';

    private const WHITE = '#FFFFFF';

    public function test_body_text_pairings_meet_aa(): void
    {
        $pairs = [
            'ink on surface (app background)' => [self::INK, self::SURFACE],
            'ink on card' => [self::INK, self::CARD],
            'crimson on card (links, primary text)' => [self::CRIMSON, self::CARD],
            'crimson on surface' => [self::CRIMSON, self::SURFACE],
            'white on crimson (primary button, marketing hero)' => [self::WHITE, self::CRIMSON],
            'white on crimson-dark (hover state)' => [self::WHITE, self::CRIMSON_DARK],
            'green on card (success, completion ticks)' => [self::GREEN, self::CARD],
            'gold-ink on card (achievement text on light)' => [self::GOLD_INK, self::CARD],
        ];

        foreach ($pairs as $label => [$fg, $bg]) {
            $ratio = $this->contrast($fg, $bg);

            $this->assertGreaterThanOrEqual(
                4.5,
                $ratio,
                sprintf('%s is %.2f:1 — below the 4.5:1 WCAG AA floor for body text.', $label, $ratio),
            );
        }
    }

    public function test_the_base_gold_is_still_unusable_as_text_on_light(): void
    {
        // CLAUDE.md forbids --uprl-gold as TEXT on light surfaces and provides
        // --uprl-gold-ink instead. Asserting the reason keeps the rule from being
        // "re-examined" by someone who assumes it was over-cautious.
        $this->assertLessThan(
            4.5,
            $this->contrast(self::GOLD, self::CARD),
            'Base gold now passes AA on white — the gold-ink rule may be reconsidered.',
        );

        $this->assertGreaterThanOrEqual(4.5, $this->contrast(self::GOLD_INK, self::CARD));
    }

    public function test_focus_rings_are_visible_against_their_own_backgrounds(): void
    {
        // A focus indicator is a non-text UI boundary: AA asks for 3:1.
        $this->assertGreaterThanOrEqual(
            3.0,
            $this->contrast(self::CRIMSON, self::SURFACE),
            'The standard crimson focus ring is not distinguishable from the app background.',
        );

        // The reason focus-ring-inverse exists: a crimson ring on a crimson hero is no
        // ring at all, so anything interactive inside a hero uses a white one.
        $this->assertLessThan(
            3.0,
            $this->contrast(self::CRIMSON, self::CRIMSON),
            'Sanity check on the contrast maths itself.',
        );

        $this->assertGreaterThanOrEqual(
            3.0,
            $this->contrast(self::WHITE, self::CRIMSON),
            'The inverse focus ring must be visible on the crimson marketing hero.',
        );
    }

    /**
     * WCAG 2.1 relative-contrast ratio between two sRGB hex colours.
     */
    private function contrast(string $hexA, string $hexB): float
    {
        $l1 = $this->relativeLuminance($hexA);
        $l2 = $this->relativeLuminance($hexB);

        [$light, $dark] = $l1 >= $l2 ? [$l1, $l2] : [$l2, $l1];

        return ($light + 0.05) / ($dark + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        $channels = [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];

        // sRGB → linear.
        $linear = array_map(
            fn (float $c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
