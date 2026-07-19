<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'user_id' => User::factory(),
            'version' => 1,
            'body' => '<p>'.fake()->paragraph().'</p>',
            'submitted_at' => now(),
            'is_late' => false,
            'status' => SubmissionStatus::Submitted->value,
        ];
    }

    public function version(int $n): static
    {
        return $this->state(fn () => ['version' => $n]);
    }

    public function late(): static
    {
        return $this->state(fn () => ['is_late' => true]);
    }

    public function graded(): static
    {
        return $this->state(fn () => ['status' => SubmissionStatus::Graded->value]);
    }

    public function returned(): static
    {
        return $this->state(fn () => [
            'status' => SubmissionStatus::ReturnedForResubmission->value,
            'return_note' => fake()->sentence(),
            'returned_at' => now(),
        ]);
    }
}
