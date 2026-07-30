<?php

namespace Database\Factories;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'name' => null,
            'type' => CouponType::Percentage,
            'value' => 20,
            'scope' => CouponScope::Global,
            'max_redemptions' => null,
            'per_user_limit' => 1,
            'is_active' => true,
        ];
    }

    public function percentage(float $percent): static
    {
        return $this->state(fn () => ['type' => CouponType::Percentage, 'value' => $percent]);
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn () => ['type' => CouponType::Fixed, 'value' => $amount]);
    }

    public function full(): static
    {
        return $this->state(fn () => ['type' => CouponType::Full, 'value' => 0]);
    }

    public function forCourse(Course $course): static
    {
        return $this->state(fn () => ['scope' => CouponScope::Course, 'course_id' => $course->id]);
    }

    public function forProgramme(Programme $programme): static
    {
        return $this->state(fn () => ['scope' => CouponScope::Programme, 'programme_id' => $programme->id]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['starts_at' => now()->addWeek()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
