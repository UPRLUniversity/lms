<?php

namespace Database\Factories;

use App\Models\Rubric;
use App\Models\RubricCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RubricCriterion>
 */
class RubricCriterionFactory extends Factory
{
    protected $model = RubricCriterion::class;

    public function definition(): array
    {
        return [
            'rubric_id' => Rubric::factory(),
            'title' => Str::title(fake()->words(2, true)),
            'description' => fake()->sentence(),
            'levels' => [
                ['label' => 'Excellent', 'description' => 'Exceeds expectations.', 'points' => 10],
                ['label' => 'Good', 'description' => 'Meets expectations.', 'points' => 7],
                ['label' => 'Needs work', 'description' => 'Below expectations.', 'points' => 3],
            ],
            'position' => 0,
        ];
    }

    /**
     * @param  array<int, array{label: string, description?: string, points: float|int}>  $levels
     */
    public function levels(array $levels): static
    {
        return $this->state(fn () => ['levels' => $levels]);
    }
}
