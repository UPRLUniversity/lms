<?php

namespace App\Http\Requests\Courses;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Staff enrolling a user directly into a course (from the roster or a user's page).
 * Authorization is the course-scoped enrollOthers gate.
 */
class AdminEnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = Course::find($this->input('course_id'));

        return $course !== null && $this->user()->can('enrollOthers', $course);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],

            // An override without a reason is an override nobody can account for later,
            // so the reason is required exactly when the box is ticked.
            'override_prerequisites' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'required_if_accepted:override_prerequisites', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'override_reason.required_if_accepted' => 'Give a reason for admitting this student past the prerequisite.',
        ];
    }
}
