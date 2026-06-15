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
        ];
    }
}
