<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseChange;
use App\Models\User;
use App\Support\Curriculum\ChangeSignificance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseChange>
 */
class CourseChangeFactory extends Factory
{
    protected $model = CourseChange::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'user_id' => User::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'action' => 'updated',
            'significance' => ChangeSignificance::Material->value,
            'summary' => 'The due date changed from 20 Aug 2026 to 27 Aug 2026.',
            'note' => null,
            'created_at' => now(),
        ];
    }

    public function cosmetic(): static
    {
        return $this->state(fn () => [
            'significance' => ChangeSignificance::Cosmetic->value,
            'summary' => 'Instructions updated.',
        ]);
    }

    public function withNote(string $note): static
    {
        return $this->state(fn () => ['note' => $note]);
    }
}
