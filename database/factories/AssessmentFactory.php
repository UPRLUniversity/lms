<?php

namespace Database\Factories;

use App\Enums\AssessmentPlacement;
use App\Enums\AssessmentStatus;
use App\Enums\ReviewPolicy;
use App\Enums\SelectionMode;
use App\Models\Assessment;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    public function definition(): array
    {
        $title = Str::title(fake()->words(3, true));

        return [
            'course_id' => Course::factory(),
            'module_id' => null,
            'created_by' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'instructions' => '<p>'.fake()->sentence().'</p>',
            'placement' => AssessmentPlacement::Standalone->value,
            'status' => AssessmentStatus::Draft->value,
            'selection_mode' => SelectionMode::Fixed->value,
            'passing_score' => 70,
            'max_attempts' => null,
            'time_limit_minutes' => null,
            'available_from' => null,
            'available_until' => null,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'review_policy' => ReviewPolicy::Immediately->value,
            'show_explanations' => true,
            'is_required' => true,
            'position' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => AssessmentStatus::Published->value]);
    }

    public function pooled(): static
    {
        return $this->state(fn () => ['selection_mode' => SelectionMode::Pooled->value]);
    }

    public function timed(int $minutes = 30): static
    {
        return $this->state(fn () => ['time_limit_minutes' => $minutes]);
    }

    public function maxAttempts(int $n): static
    {
        return $this->state(fn () => ['max_attempts' => $n]);
    }

    public function shuffled(): static
    {
        return $this->state(fn () => ['shuffle_questions' => true, 'shuffle_options' => true]);
    }

    public function reviewPolicy(ReviewPolicy $policy): static
    {
        return $this->state(fn () => ['review_policy' => $policy->value]);
    }

    public function preModule(int $moduleId): static
    {
        return $this->state(fn () => [
            'placement' => AssessmentPlacement::PreModule->value,
            'module_id' => $moduleId,
        ]);
    }

    public function postModule(int $moduleId): static
    {
        return $this->state(fn () => [
            'placement' => AssessmentPlacement::PostModule->value,
            'module_id' => $moduleId,
        ]);
    }

    public function window(?string $from, ?string $until): static
    {
        return $this->state(fn () => ['available_from' => $from, 'available_until' => $until]);
    }
}
