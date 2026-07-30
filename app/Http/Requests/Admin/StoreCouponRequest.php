<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Coupon::class);
    }

    /**
     * Codes are stored and compared upper-case, so normalise before the unique rule —
     * otherwise "save20" and "SAVE20" become two different codes that look identical
     * to a student typing one in.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => $this->normalisedCode()]);
        }
    }

    public function normalisedCode(): string
    {
        return Str::upper(trim((string) $this->input('code')));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9._-]+$/', Rule::unique('coupons', 'code')],
            'name' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(CouponType::values())],
            'value' => ['nullable', 'numeric', 'min:0'],
            'scope' => ['required', Rule::in(CouponScope::values())],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'programme_id' => ['nullable', 'integer', 'exists:programmes,id'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, dots, dashes or underscores only.',
            'code.unique' => 'That code already exists.',
            'expires_at.after' => 'The end date must be after the start date.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $scope = CouponScope::tryFrom((string) $this->input('scope'));
            $type = CouponType::tryFrom((string) $this->input('type'));

            // A scope has to name its target, or the coupon covers nothing.
            if ($scope === CouponScope::Course && blank($this->input('course_id'))) {
                $validator->errors()->add('course_id', 'Choose the course this code applies to.');
            }

            if ($scope === CouponScope::Programme && blank($this->input('programme_id'))) {
                $validator->errors()->add('programme_id', 'Choose the programme this code applies to.');
            }

            // Percentage and fixed are meaningless without a value; Full ignores it.
            if ($type?->usesValue() && (float) $this->input('value') <= 0) {
                $validator->errors()->add('value', 'Enter a discount greater than zero.');
            }

            if ($type === CouponType::Percentage && (float) $this->input('value') > 100) {
                $validator->errors()->add('value', 'A percentage cannot exceed 100.');
            }

            // Authorization, not just validation: an instructor may only issue
            // course-scoped codes, and only for a course they actually teach.
            if ($scope !== null && ! $this->user()->can('useScope', [Coupon::class, $scope])) {
                $validator->errors()->add('scope', 'You may only create codes for your own courses.');
            }

            if ($scope === CouponScope::Course && filled($this->input('course_id'))) {
                $course = Course::find($this->input('course_id'));

                if ($course && ! $this->user()->can('manageForCourse', [Coupon::class, $course])) {
                    $validator->errors()->add('course_id', 'You do not manage that course.');
                }
            }
        });
    }
}
