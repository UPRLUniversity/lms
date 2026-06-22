<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    protected $model = Attempt::class;

    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'user_id' => User::factory(),
            'attempt_number' => 1,
            'started_at' => now(),
            'submitted_at' => null,
            'expires_at' => null,
            'score' => null,
            'max_score' => null,
            'percentage' => null,
            'passed' => null,
            'status' => AttemptStatus::InProgress->value,
            'layout' => ['questions' => []],
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => AttemptStatus::Submitted->value,
            'submitted_at' => now(),
        ]);
    }

    public function graded(int $percentage = 80, bool $passed = true): static
    {
        return $this->state(fn () => [
            'status' => AttemptStatus::Graded->value,
            'submitted_at' => now(),
            'percentage' => $percentage,
            'passed' => $passed,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subHour(),
            'expires_at' => now()->subMinute(),
        ]);
    }
}
