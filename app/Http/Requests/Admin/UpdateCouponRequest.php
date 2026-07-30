<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Editing a coupon deliberately cannot change its `code` or its `scope`.
 *
 * The code may already be in circulation — a student holding a printed or emailed code
 * must keep being able to use it. And re-scoping a live code silently changes what past
 * redemptions meant. Either change is a new coupon, not an edit.
 */
class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('coupon'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(CouponType::values())],
            'value' => ['nullable', 'numeric', 'min:0'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = CouponType::tryFrom((string) $this->input('type'));

            if ($type?->usesValue() && (float) $this->input('value') <= 0) {
                $validator->errors()->add('value', 'Enter a discount greater than zero.');
            }

            if ($type === CouponType::Percentage && (float) $this->input('value') > 100) {
                $validator->errors()->add('value', 'A percentage cannot exceed 100.');
            }

            // Lowering the cap below what has already been redeemed would make the
            // coupon retroactively over-subscribed.
            $coupon = $this->route('coupon');
            $max = $this->input('max_redemptions');

            if (filled($max) && $coupon && (int) $max < $coupon->redemptionCount()) {
                $validator->errors()->add(
                    'max_redemptions',
                    "This code has already been used {$coupon->redemptionCount()} times, so the limit cannot be lower than that.",
                );
            }
        });
    }
}
