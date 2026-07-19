<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The grading workspace form. Shape only — the authoritative rubric math (level
 * validity, totals) is recomputed in AssignmentGradingService, never trusted from here.
 */
class GradeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('grade', $this->route('submission'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'criteria' => ['sometimes', 'array'],
            'criteria.*' => ['integer', 'min:0'],
            'points' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:100000'],
            'and_next' => ['sometimes', 'boolean'],
        ];
    }
}
