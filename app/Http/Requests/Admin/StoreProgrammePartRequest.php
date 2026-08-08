<?php

namespace App\Http\Requests\Admin;

use App\Models\ProgrammePart;
use Illuminate\Foundation\Http\FormRequest;

class StoreProgrammePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ProgrammePart::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'programme_id' => ['required', 'integer', 'exists:programmes,id'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            // The credit total the printed curriculum states for this part. Left null
            // when the prospectus doesn't state one — the UI then shows only the
            // computed sum rather than asserting a mismatch against nothing.
            'credit_target' => ['nullable', 'integer', 'min:0', 'max:500'],
            // The bar for PROGRESSION only, when the registrar wants one different
            // from the published total. Null (the norm) means "use credit_target".
            'unlock_credits' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];
    }
}
