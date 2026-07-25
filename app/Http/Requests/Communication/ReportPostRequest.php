<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Flagging a forum post for admin review. Authorized against the post-level "report"
 * ability (any forum member except the post's own author).
 */
class ReportPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('report', $this->route('post'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
