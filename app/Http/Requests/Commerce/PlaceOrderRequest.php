<?php

namespace App\Http\Requests\Commerce;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Note what is NOT here: no prices, no subtotal, no total. Money is never accepted
     * from the request — CheckoutService re-resolves every figure from PricingService
     * inside the order transaction. A posted amount is not validated, it is ignored.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'string', 'max:40'],

            // Billing details are a record of who paid, not an address we ship to, so
            // they are light and mostly optional.
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],

            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms.accepted' => 'Please agree to the terms of use and privacy policy.',
        ];
    }

    /**
     * The billing snapshot stored on the order. Falls back to the account's own name
     * and e-mail so a receipt is never anonymous even if the buyer skipped the fields.
     *
     * @return array<string, string>
     */
    public function billing(): array
    {
        $user = $this->user();

        return array_filter([
            'first_name' => $this->string('first_name')->trim()->value(),
            'last_name' => $this->string('last_name')->trim()->value(),
            'email' => $this->string('email')->trim()->value() ?: $user->email,
            'phone' => $this->string('phone')->trim()->value(),
            'country' => $this->string('country')->trim()->value(),
            'city' => $this->string('city')->trim()->value(),
            'address' => $this->string('address')->trim()->value(),
            'name' => $user->name,
        ], fn ($value) => filled($value));
    }
}
