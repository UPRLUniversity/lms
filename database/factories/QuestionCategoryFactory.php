<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QuestionCategory>
 */
class QuestionCategoryFactory extends Factory
{
    protected $model = QuestionCategory::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
        ];
    }

    /**
     * A global (course-less) category.
     */
    public function global(): static
    {
        return $this->state(fn () => ['course_id' => null]);
    }

    public function forCourse(Course $course): static
    {
        return $this->state(fn () => ['course_id' => $course->id]);
    }
}
