<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * An instructor messaging every enrolled student of a course as one group thread.
 * Authorized against the course-level "messageCourseGroup" ability (same ownership rule
 * as editing the course).
 */
class MessageAllEnrolledRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('messageCourseGroup', $this->route('course'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
        ];
    }
}
