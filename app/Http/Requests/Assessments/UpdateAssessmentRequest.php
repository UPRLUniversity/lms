<?php

namespace App\Http\Requests\Assessments;

use App\Enums\ReviewPolicy;
use App\Enums\SelectionMode;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the assessment settings panel.
 */
class UpdateAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('assessment'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:50000'],
            'selection_mode' => ['required', 'string', 'in:'.implode(',', SelectionMode::values())],
            'passing_score' => ['required', 'integer', 'min:0', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after_or_equal:available_from'],
            'review_policy' => ['required', 'string', 'in:'.implode(',', ReviewPolicy::values())],
            'shuffle_questions' => ['boolean'],
            'shuffle_options' => ['boolean'],
            'show_explanations' => ['boolean'],
            'is_required' => ['boolean'],
            'counts_toward_grade' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();

        // Normalise checkbox booleans so an unchecked box persists as false.
        foreach (['shuffle_questions', 'shuffle_options', 'show_explanations', 'is_required', 'counts_toward_grade'] as $flag) {
            $data[$flag] = $this->boolean($flag);
        }

        return $data;
    }
}
