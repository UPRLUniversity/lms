<?php

namespace Database\Factories;

use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Rubric>
 */
class RubricFactory extends Factory
{
    protected $model = Rubric::class;

    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->words(2, true)).' Rubric',
            'created_by' => User::factory(),
        ];
    }

    /**
     * A realistic 3-criteria × 3-level grid (max 30 points).
     */
    public function withCriteria(): static
    {
        return $this->afterCreating(function (Rubric $rubric) {
            foreach (['Argument & analysis', 'Evidence & sources', 'Clarity & structure'] as $i => $title) {
                RubricCriterion::factory()->create([
                    'rubric_id' => $rubric->id,
                    'title' => $title,
                    'position' => $i,
                ]);
            }
        });
    }
}
