<?php

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttemptAnswer>
 */
class AttemptAnswerFactory extends Factory
{
    protected $model = AttemptAnswer::class;

    public function definition(): array
    {
        return [
            'attempt_id' => Attempt::factory(),
            'question_id' => Question::factory(),
            'response' => null,
            'is_correct' => null,
            'points_awarded' => null,
            'points_possible' => 1,
            'feedback' => null,
            'graded_by' => null,
            'graded_at' => null,
            'flagged' => false,
        ];
    }
}
