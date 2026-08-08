<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgrammePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('part'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'credit_target' => ['nullable', 'integer', 'min:0', 'max:500'],
            // The bar for PROGRESSION only, when the registrar wants one different
            // from the published total. Null (the norm) means "use credit_target".
            'unlock_credits' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];
    }
}
