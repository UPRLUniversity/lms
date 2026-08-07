<?php

namespace Tests\Feature\Grades;

use App\Enums\GradeDisplayMode;
use App\Models\GradeScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GradeScale::bandFor() mapper edge cases (0, 100, exact boundaries) and display
 * rendering per the scale's settings.
 */
class GradeScaleMapperTest extends TestCase
{
    use RefreshDatabase;

    private function nucStandard(): GradeScale
    {
        $scale = GradeScale::factory()->default()->create([
            'name' => 'NUC Standard (5.0)',
            'scale_limit' => 5.0,
            'display_mode' => GradeDisplayMode::Both->value,
            'show_scale_limit' => true,
            'separator' => '/',
        ]);

        $scale->bands()->createMany([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success', 'position' => 0],
            ['label' => 'B', 'grade_point' => 4.0, 'is_pass' => true, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold', 'position' => 1],
            ['label' => 'C', 'grade_point' => 3.0, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink', 'position' => 2],
            ['label' => 'D', 'grade_point' => 2.0, 'is_pass' => true, 'min_percent' => 45, 'max_percent' => 49, 'color' => 'neutral', 'position' => 3],
            ['label' => 'E', 'grade_point' => 1.0, 'is_pass' => true, 'min_percent' => 40, 'max_percent' => 44, 'color' => 'neutral', 'position' => 4],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 39, 'color' => 'crimson', 'position' => 5],
        ]);

        return $scale->fresh('bands');
    }

    public function test_a_43_percent_score_maps_to_exactly_one_band(): void
    {
        $scale = $this->nucStandard();

        $matches = $scale->bands->filter(fn ($b) => $b->covers(43));
        $this->assertCount(1, $matches);
        $this->assertSame('E', $scale->bandFor(43)->label); // 40–44
    }

    public function test_boundary_scores_map_correctly(): void
    {
        $scale = $this->nucStandard();

        $this->assertSame('F', $scale->bandFor(0)->label);
        $this->assertSame('A', $scale->bandFor(100)->label);
        $this->assertSame('F', $scale->bandFor(39)->label);
        $this->assertSame('E', $scale->bandFor(40)->label);
        $this->assertSame('D', $scale->bandFor(49)->label);
        $this->assertSame('C', $scale->bandFor(50)->label);
        $this->assertSame('B', $scale->bandFor(69)->label);
        $this->assertSame('A', $scale->bandFor(70)->label);
    }

    public function test_a_fractional_percent_rounds_to_the_nearest_band(): void
    {
        $scale = $this->nucStandard();

        // 59.5 rounds to 60 → B, not C.
        $this->assertSame('B', $scale->bandFor(59.5)->label);
    }

    public function test_nuc_default_renders_percent_letter_and_point_over_limit(): void
    {
        $scale = $this->nucStandard();

        $this->assertSame('68% · B · 4.0/5.0', $scale->formatResult(68));
    }

    public function test_switching_display_mode_to_points_hides_the_letter(): void
    {
        $scale = $this->nucStandard();
        $scale->update(['display_mode' => GradeDisplayMode::Points->value]);
        $scale->refresh();

        $rendered = $scale->formatResult(68);
        $this->assertStringNotContainsString('B', $rendered);
        $this->assertStringContainsString('4.0/5.0', $rendered);
    }

    public function test_switching_display_mode_to_letter_hides_the_point(): void
    {
        $scale = $this->nucStandard();
        $scale->update(['display_mode' => GradeDisplayMode::Letter->value]);
        $scale->refresh();

        $rendered = $scale->formatResult(68);
        $this->assertStringContainsString('B', $rendered);
        $this->assertStringNotContainsString('4.0/5.0', $rendered);
    }
}
