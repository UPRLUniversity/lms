<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Posting a reply. Authorized against the thread-level "reply" ability, which also
 * enforces the lock (a locked thread accepts no new replies except from moderators).
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('reply', $this->route('thread'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:50000'],
            // One level of nesting: a reply may point at a top-level post. Membership of
            // this thread is re-checked in the controller.
            'parent_id' => ['nullable', 'integer', 'exists:forum_posts,id'],
        ];
    }
}
