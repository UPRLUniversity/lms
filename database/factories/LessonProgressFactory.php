<?php

namespace Database\Factories;

use App\Enums\LessonProgressStatus;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lesson_id' => Lesson::factory(),
            'status' => LessonProgressStatus::NotStarted->value,
            'completed_at' => null,
            'seconds_spent' => 0,
            'last_position_seconds' => 0,
        ];
    }

    public function inProgress(int $position = 30): static
    {
        return $this->state(fn () => [
            'status' => LessonProgressStatus::InProgress->value,
            'last_position_seconds' => $position,
            'seconds_spent' => $position,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => LessonProgressStatus::Completed->value,
            'completed_at' => now(),
        ]);
    }
}
