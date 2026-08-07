<?php

namespace App\Http\Requests\Admin;

use App\Enums\GradeDisplayMode;
use App\Models\GradeScale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The scale grid builder, posted wholesale: settings + ordered bands. Cross-band
 * invariants (coverage, monotonicity, scale-limit ceiling) are enforced server-side by
 * GradeBandValidator inside GradeScaleService::save() — this request only validates
 * per-field shape.
 */
class GradeScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scale = $this->route('gradeScale');

        return $scale ? Gate::allows('update', $scale) : Gate::allows('create', GradeScale::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'scale_limit' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
            'display_mode' => ['required', 'in:'.implode(',', GradeDisplayMode::values())],
            'show_scale_limit' => ['nullable', 'boolean'],
            'separator' => ['nullable', 'string', 'max:10'],

            'bands' => ['required', 'array', 'min:2', 'max:15'],
            'bands.*.label' => ['required', 'string', 'max:50'],
            'bands.*.grade_point' => ['required', 'numeric', 'min:0', 'max:100'],
            // Required, not nullable: a band must state whether it passes. The editor
            // always posts it (hidden field alongside the toggle), so an absent value
            // means a hand-rolled request, which should be refused rather than defaulted.
            'bands.*.is_pass' => ['required', 'boolean'],
            'bands.*.min_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'bands.*.max_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'bands.*.color' => ['nullable', 'in:success,gold,crimson,ink,neutral'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bands.required' => 'Add at least two bands to the scale.',
            'bands.min' => 'A grade scale needs at least two bands.',
        ];
    }
}
