<?php

namespace Database\Factories;

use App\Models\ForumPost;
use App\Models\ForumPostReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForumPostReport>
 */
class ForumPostReportFactory extends Factory
{
    protected $model = ForumPostReport::class;

    public function definition(): array
    {
        return [
            'forum_post_id' => ForumPost::factory(),
            'user_id' => User::factory(),
            'reason' => fake()->sentence(),
            'resolved_at' => null,
            'resolved_by' => null,
        ];
    }
}
