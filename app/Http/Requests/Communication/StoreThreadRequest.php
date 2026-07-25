<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Opening a forum thread. Authorized against the course-level "participate" gate, so
 * the read-only auditor and non-members are turned away before validation runs.
 */
class StoreThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('participateInForum', $this->route('course'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            // Optional "Discuss this lesson" link; ownership by the course is re-checked
            // in the controller.
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
        ];
    }
}
