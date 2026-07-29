<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class UpdateProgrammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('programme'));
    }

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
            'code' => [
                'required', 'string', 'max:12', 'alpha_num',
                Rule::unique('programmes', 'code')->ignore($this->route('programme')->id),
            ],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'registration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'administration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'per_paper_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'is_active' => ['nullable', 'boolean'],
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
