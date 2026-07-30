<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'session_token' => null];
    }

    public function guest(string $token): static
    {
        return $this->state(fn () => ['user_id' => null, 'session_token' => $token]);
    }
}
