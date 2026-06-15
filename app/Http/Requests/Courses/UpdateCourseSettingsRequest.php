<?php

namespace App\Http\Requests\Courses;

use App\Enums\CourseLevel;
use App\Enums\CourseVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('course'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $courseId = $this->route('course')->id;
        $coverMaxKb = (int) config('media.purposes.course_covers.max_kb', 4096);

        return [
            'title' => ['required', 'string', 'max:200'],
            'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')->ignore($courseId)],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'level' => ['required', Rule::in(CourseLevel::values())],
            'visibility' => ['required', Rule::in(CourseVisibility::values())],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'learning_objectives' => ['nullable', 'array', 'max:25'],
            'learning_objectives.*' => ['nullable', 'string', 'max:255'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', "max:{$coverMaxKb}"],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * Objectives without text are dropped so empty rows never persist.
     *
     * @return array<int, string>
     */
    public function objectives(): array
    {
        return collect($this->input('learning_objectives', []))
            ->map(fn ($o) => trim((string) $o))
            ->filter()
            ->values()
            ->all();
    }
}
