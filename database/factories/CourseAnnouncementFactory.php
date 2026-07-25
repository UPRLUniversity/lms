<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseAnnouncement>
 */
class CourseAnnouncementFactory extends Factory
{
    protected $model = CourseAnnouncement::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }
}
