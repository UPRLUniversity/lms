<?php

namespace Database\Factories;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'status' => EnrollmentStatus::Active->value,
            'source' => EnrollmentSource::Self->value,
            'enrolled_at' => now(),
            'approved_by' => null,
            'decision_note' => null,
        ];
    }

    public function for_(User $user, Course $course): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);
    }

    public function status(EnrollmentStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }

    public function pending(): static
    {
        return $this->status(EnrollmentStatus::Pending)
            ->state(fn () => ['source' => EnrollmentSource::Self->value]);
    }

    public function active(): static
    {
        return $this->status(EnrollmentStatus::Active);
    }

    public function waitlisted(): static
    {
        return $this->status(EnrollmentStatus::Waitlisted);
    }

    public function completed(): static
    {
        return $this->status(EnrollmentStatus::Completed);
    }

    public function withdrawn(): static
    {
        return $this->status(EnrollmentStatus::Withdrawn);
    }
}
