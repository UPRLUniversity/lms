<?php

namespace App\Http\Requests\Courses;

use App\Enums\CourseLevel;
use App\Enums\CourseRequirement;
use App\Enums\CourseVisibility;
use App\Enums\EnrollmentMode;
use App\Enums\ProgressionMode;
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
            'enrollment_mode' => ['required', Rule::in(EnrollmentMode::values())],
            'progression_mode' => ['required', Rule::in(ProgressionMode::values())],
            'grade_scale_id' => ['nullable', 'integer', 'exists:grade_scales,id'],
            'certificate_template_id' => ['nullable', 'integer', 'exists:certificate_templates,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'enrollment_opens_at' => ['nullable', 'date'],
            'enrollment_closes_at' => ['nullable', 'date', 'after_or_equal:enrollment_opens_at'],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'learning_objectives' => ['nullable', 'array', 'max:25'],
            'learning_objectives.*' => ['nullable', 'string', 'max:255'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', "max:{$coverMaxKb}"],

            // Programme placements. programme_id rides along so the repeater can filter
            // parts client-side, but it is NOT validated as authoritative — the part id
            // already determines the programme, and trusting a mismatched pair would let
            // a crafted post file a course under a programme it never chose.
            'placements' => ['nullable', 'array', 'max:10'],
            'placements.*.programme_part_id' => ['nullable', 'integer', 'exists:programme_parts,id'],
            'placements.*.credit_load' => ['nullable', 'integer', 'min:0', 'max:60'],
            'placements.*.requirement' => ['nullable', Rule::in(CourseRequirement::values())],
            'placements.*.is_primary' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'placements.*.programme_part_id.exists' => 'One of the programme parts you chose no longer exists.',
            'placements.max' => 'A course can be placed in at most 10 programme parts.',
        ];
    }

    /**
     * Placement rows with no part chosen are dropped — the repeater always renders an
     * empty "Choose…" option, and a half-filled row is a user who changed their mind,
     * not an error worth blocking the whole settings save over.
     *
     * @return array<int, array<string, mixed>>
     */
    public function placements(): array
    {
        return collect($this->input('placements', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['programme_part_id']))
            ->map(fn ($row) => [
                'programme_part_id' => (int) $row['programme_part_id'],
                'credit_load' => $row['credit_load'] ?? null,
                'requirement' => $row['requirement'] ?? null,
                'is_primary' => filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();
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
