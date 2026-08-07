<?php

namespace Database\Factories;

use App\Enums\GradeDisplayMode;
use App\Enums\GradeScaleStatus;
use App\Models\GradeScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeScale>
 */
class GradeScaleFactory extends Factory
{
    protected $model = GradeScale::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true).' Scale',
            'scale_limit' => 5.00,
            'is_default' => false,
            'display_mode' => GradeDisplayMode::Both->value,
            'show_scale_limit' => true,
            'separator' => '/',
            'status' => GradeScaleStatus::Active->value,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /**
     * A standard 4-band A/B/C/F scale, e.g. for quick test/demo setup. Pass mark 50%.
     */
    public function withBands(): static
    {
        return $this->afterCreating(function (GradeScale $scale) {
            $bands = [
                ['label' => 'A', 'grade_point' => (float) $scale->scale_limit, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success'],
                ['label' => 'B', 'grade_point' => (float) $scale->scale_limit * 0.8, 'is_pass' => true, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold'],
                ['label' => 'C', 'grade_point' => (float) $scale->scale_limit * 0.6, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink'],
                ['label' => 'F', 'grade_point' => 0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson'],
            ];

            foreach ($bands as $position => $band) {
                $scale->bands()->create($band + ['position' => $position]);
            }
        });
    }
}
