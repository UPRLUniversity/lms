<?php

namespace Database\Factories;

use App\Enums\OrderItemKind;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'kind' => OrderItemKind::Course,
            'course_id' => Course::factory(),
            'title' => fake()->sentence(4),
            'unit_price' => 7000,
            'line_total' => 7000,
        ];
    }

    public function forCourse(Course $course, float $price = 7000): static
    {
        return $this->state(fn () => [
            'kind' => OrderItemKind::Course,
            'course_id' => $course->id,
            'programme_id' => null,
            'title' => $course->title,
            'unit_price' => $price,
            'line_total' => $price,
        ]);
    }

    public function registrationFee(Programme $programme, float $price = 20000): static
    {
        return $this->state(fn () => [
            'kind' => OrderItemKind::RegistrationFee,
            'course_id' => null,
            'programme_id' => $programme->id,
            'title' => $programme->name.' — Registration fee',
            'unit_price' => $price,
            'line_total' => $price,
        ]);
    }
}
