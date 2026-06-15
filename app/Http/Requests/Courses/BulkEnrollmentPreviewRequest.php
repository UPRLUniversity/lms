<?php

namespace App\Http\Requests\Courses;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Uploading an enrolment CSV for preview. Admin-only (super-admin included).
 */
class BulkEnrollmentPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole([Role::Admin->value, Role::SuperAdmin->value]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:4096',
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/octet-stream',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Please upload a .csv file.',
            'file.max' => 'The file may not be larger than 4 MB.',
        ];
    }
}
