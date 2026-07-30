<?php

namespace Database\Factories;

use App\Enums\PaymentEnvironment;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'key' => 'sandbox',
            'label' => 'Sandbox (test only)',
            'is_enabled' => true,
            'environment' => PaymentEnvironment::Test,
            'config' => [],
            'position' => 0,
        ];
    }

    public function paystack(bool $configured = true): static
    {
        return $this->state(fn () => [
            'key' => 'paystack',
            'label' => 'Paystack',
            'config' => $configured
                ? ['public_key' => 'pk_test_x', 'secret_key' => 'sk_test_secret']
                : ['public_key' => '', 'secret_key' => ''],
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn () => [
            'key' => 'bank_transfer',
            'label' => 'Bank transfer',
            'instructions' => '<p>Pay into UPRL, 0123456789, Demo Bank.</p>',
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
