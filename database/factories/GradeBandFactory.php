<?php

namespace Database\Factories;

use App\Models\GradeBand;
use App\Models\GradeScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeBand>
 */
class GradeBandFactory extends Factory
{
    protected $model = GradeBand::class;

    public function definition(): array
    {
        return [
            'grade_scale_id' => GradeScale::factory(),
            'label' => 'A',
            'grade_point' => 5.00,
            'min_percent' => 70,
            'max_percent' => 100,
            'color' => 'success',
            'position' => 0,
        ];
    }
}
