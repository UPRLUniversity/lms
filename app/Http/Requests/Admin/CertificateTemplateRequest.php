<?php

namespace App\Http\Requests\Admin;

use App\Enums\CertificateLayout;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CertificateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('certificateTemplate');

        return $template ? Gate::allows('update', $template) : Gate::allows('create', CertificateTemplate::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'layout' => ['required', Rule::in(CertificateLayout::values())],
            'is_default' => ['nullable', 'boolean'],
            'show_grade' => ['nullable', 'boolean'],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            'signatory_one.name' => ['nullable', 'string', 'max:255'],
            'signatory_one.title' => ['nullable', 'string', 'max:255'],
            'signatory_one.signature_media_id' => ['nullable', 'integer', 'exists:media,id'],

            'signatory_two.name' => ['nullable', 'string', 'max:255'],
            'signatory_two.title' => ['nullable', 'string', 'max:255'],
            'signatory_two.signature_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accent_color.regex' => 'Enter a hex colour like #C8102E, or leave blank to use the layout default.',
        ];
    }
}
