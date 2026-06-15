<?php

namespace App\Http\Requests\Courses;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirming a previewed import. Carries the staged file's token (a UUID basename),
 * never the file again — the rows the admin reviewed are re-read and re-validated
 * server-side from the staged copy.
 */
class BulkEnrollmentImportRequest extends FormRequest
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
            // A bare UUID so the token can only ever name a file in the staging dir.
            'token' => ['required', 'string', 'uuid'],
        ];
    }
}
