<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'reference' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'subtotal' => 0,
            'discount_total' => 0,
            'total' => 0,
            'currency' => 'NGN',
            'payment_method_key' => 'sandbox',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Paid, 'paid_at' => now()]);
    }

    public function awaitingPayment(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::AwaitingPayment, 'payment_method_key' => 'bank_transfer']);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Failed]);
    }

    public function totalling(float $amount): static
    {
        return $this->state(fn () => ['subtotal' => $amount, 'total' => $amount]);
    }
}
