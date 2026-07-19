<?php

namespace App\Http\Requests\Assignments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The rubric builder grid, posted wholesale: name + ordered criteria rows, each with
 * ordered level columns. Requires at least one criterion with at least two levels so
 * a rubric always offers a real choice.
 */
class RubricRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rubric = $this->route('rubric');

        return $rubric ? Gate::allows('manage', $rubric) : Gate::allows('create', \App\Models\Rubric::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'criteria' => ['required', 'array', 'min:1', 'max:20'],
            'criteria.*.title' => ['required', 'string', 'max:255'],
            'criteria.*.description' => ['nullable', 'string', 'max:2000'],
            'criteria.*.levels' => ['required', 'array', 'min:2', 'max:8'],
            'criteria.*.levels.*.label' => ['required', 'string', 'max:100'],
            'criteria.*.levels.*.description' => ['nullable', 'string', 'max:2000'],
            'criteria.*.levels.*.points' => ['required', 'numeric', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'criteria.required' => 'Add at least one criterion to the rubric.',
            'criteria.*.levels.min' => 'Each criterion needs at least two levels to choose between.',
            'criteria.*.levels.*.points.required' => 'Every level needs a points value.',
        ];
    }
}
