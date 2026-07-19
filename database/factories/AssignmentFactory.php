<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        $title = Str::title(fake()->words(3, true));

        return [
            'course_id' => Course::factory(),
            'module_id' => null,
            'created_by' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'instructions' => '<p>'.fake()->sentence().'</p>',
            'type' => AssignmentType::Either->value,
            'due_at' => null,
            'allow_late' => false,
            'max_points' => 100,
            'rubric_id' => null,
            'status' => AssignmentStatus::Draft->value,
            'is_required' => true,
            'position' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => AssignmentStatus::Published->value]);
    }

    public function type(AssignmentType $type): static
    {
        return $this->state(fn () => ['type' => $type->value]);
    }

    public function dueAt(?string $due): static
    {
        return $this->state(fn () => ['due_at' => $due]);
    }

    public function allowLate(): static
    {
        return $this->state(fn () => ['allow_late' => true]);
    }

    public function optional(): static
    {
        return $this->state(fn () => ['is_required' => false]);
    }

    public function forModule(int $moduleId): static
    {
        return $this->state(fn () => ['module_id' => $moduleId]);
    }
}
