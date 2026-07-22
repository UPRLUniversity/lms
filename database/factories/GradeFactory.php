<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'grader_id' => User::factory(),
            'criterion_scores' => null,
            'points_total' => fake()->numberBetween(40, 100),
            'feedback' => '<p>'.fake()->sentence().'</p>',
            'graded_at' => now(),
        ];
    }
}
