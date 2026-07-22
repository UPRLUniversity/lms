<?php

namespace App\Http\Requests\Assignments;

use App\Enums\AssignmentType;
use App\Models\Rubric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The assignment builder's settings form. Ownership checks live here: the module must
 * belong to the assignment's course and the rubric must be attachable by this user.
 */
class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', $this->route('assignment'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:100000'],
            'type' => ['required', 'string', Rule::in(AssignmentType::values())],
            'due_at' => ['nullable', 'date'],
            'allow_late' => ['sometimes', 'boolean'],
            'max_points' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'is_required' => ['sometimes', 'boolean'],
            'module_id' => [
                'nullable',
                'integer',
                Rule::exists('modules', 'id')->where('course_id', $course->id),
            ],
            'rubric_id' => ['nullable', 'integer', 'exists:rubrics,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $rubricId = $this->input('rubric_id');
            if (! $rubricId) {
                return;
            }

            $rubric = Rubric::find($rubricId);
            if (! $rubric || ! Gate::allows('view', $rubric)) {
                $validator->errors()->add('rubric_id', 'Choose one of your own rubrics.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_points.min' => 'Points must be zero or more — and more than zero to publish.',
        ];
    }
}
