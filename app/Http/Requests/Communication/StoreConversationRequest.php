<?php

namespace App\Http\Requests\Communication;

use App\Enums\ConversationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Starting a new conversation from the composer. Direct (a single recipient) is open to
 * anyone who may use messaging; group requires the group-creation capability. Whether
 * the chosen people are actually reachable (a shared course) is enforced in the
 * controller/service.
 */
class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! Gate::allows('useMessaging')) {
            return false;
        }

        return $this->input('type') === ConversationType::Group->value
            ? Gate::allows('createGroupConversation')
            : true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([ConversationType::Direct->value, ConversationType::Group->value])],
            'body' => ['required', 'string', 'max:50000'],

            // Direct: exactly one recipient.
            'recipient_id' => ['required_if:type,'.ConversationType::Direct->value, 'integer', 'exists:users,id'],

            // Group: a subject + at least one other participant.
            'subject' => ['required_if:type,'.ConversationType::Group->value, 'nullable', 'string', 'max:255'],
            'participant_ids' => ['required_if:type,'.ConversationType::Group->value, 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ];
    }
}
