<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentPoolRule;
use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentPoolRule>
 */
class AssessmentPoolRuleFactory extends Factory
{
    protected $model = AssessmentPoolRule::class;

    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'category_id' => QuestionCategory::factory(),
            'difficulty' => null,
            'count' => 5,
            'position' => 0,
        ];
    }
}
