<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumThread>
 */
class ForumThreadFactory extends Factory
{
    protected $model = ForumThread::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => User::factory(),
            'lesson_id' => null,
            'title' => rtrim(fake()->sentence(6), '.').'?',
            'body' => '<p>'.fake()->paragraph().'</p>',
            'is_pinned' => false,
            'is_locked' => false,
            'answer_post_id' => null,
            'last_activity_at' => now(),
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }
}
