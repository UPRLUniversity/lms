<?php

namespace Database\Factories;

use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use App\Enums\EnrollmentMode;
use App\Enums\ProgressionMode;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));
        $code = Str::upper(fake()->lexify('???')).fake()->numberBetween(100, 499);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'code' => $code,
            'department_id' => Department::factory(),
            'level' => fake()->randomElement(CourseLevel::values()),
            'summary' => fake()->sentence(14),
            'description' => '<p>'.fake()->paragraph(4).'</p>',
            'learning_objectives' => [
                fake()->sentence(6),
                fake()->sentence(6),
                fake()->sentence(6),
            ],
            'duration_minutes' => fake()->numberBetween(120, 1200),
            'status' => CourseStatus::Draft->value,
            'visibility' => CourseVisibility::PublicCatalogue->value,
            'created_by' => User::factory(),
            'published_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => CourseStatus::Draft->value, 'published_at' => null]);
    }

    public function review(): static
    {
        return $this->state(fn () => ['status' => CourseStatus::Review->value, 'published_at' => null]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => CourseStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => CourseStatus::Archived->value]);
    }

    public function enrolledOnly(): static
    {
        return $this->state(fn () => ['visibility' => CourseVisibility::EnrolledOnly->value]);
    }

    public function mode(EnrollmentMode $mode): static
    {
        return $this->state(fn () => ['enrollment_mode' => $mode->value]);
    }

    public function approvalMode(): static
    {
        return $this->mode(EnrollmentMode::Approval);
    }

    public function inviteOnly(): static
    {
        return $this->mode(EnrollmentMode::InviteOnly);
    }

    public function sequential(): static
    {
        return $this->state(fn () => ['progression_mode' => ProgressionMode::Sequential->value]);
    }

    /**
     * A capped course (defaults to the spec's worked example of 3 seats).
     */
    public function withCapacity(int $capacity = 3): static
    {
        return $this->state(fn () => ['capacity' => $capacity]);
    }

    /**
     * An enrolment window relative to now (null bounds = unbounded that side).
     */
    public function window(?\DateTimeInterface $opens, ?\DateTimeInterface $closes): static
    {
        return $this->state(fn () => [
            'enrollment_opens_at' => $opens,
            'enrollment_closes_at' => $closes,
        ]);
    }

    /**
     * Attach an instructor (lead by default) once the course exists.
     */
    public function withInstructor(User $user, bool $lead = true): static
    {
        return $this->afterCreating(function (Course $course) use ($user, $lead) {
            $course->instructors()->syncWithoutDetaching([$user->id => ['is_lead' => $lead]]);
        });
    }
}
