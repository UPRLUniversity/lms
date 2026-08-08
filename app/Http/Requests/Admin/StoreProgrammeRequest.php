<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaPurpose;
use App\Enums\ProgressionRule;
use App\Models\Programme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProgrammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Programme::class);
    }

    /**
     * Programme codes are the short identifier used in URLs, seeders and the fee
     * schedule (CPR, DPR, NPV). Normalise before validating so "cpr " and "CPR"
     * collide on the unique rule rather than creating a near-duplicate.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => Str::upper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cover = MediaPurpose::ProgrammeCovers;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:12', 'alpha_num', Rule::unique('programmes', 'code')],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],

            // Fees are optional on create — a programme can be set up before its
            // schedule is agreed, and 0 legitimately means "free".
            'registration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'administration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'per_paper_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],

            'is_active' => ['nullable', 'boolean'],
            'progression_rule' => ['required', 'in:'.implode(',', ProgressionRule::values())],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.$cover->maxKb()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Another programme already uses that code.',
            'code.alpha_num' => 'The code may contain letters and numbers only — e.g. CPR.',
        ];
    }
}
