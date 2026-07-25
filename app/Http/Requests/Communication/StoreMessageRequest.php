<?php

namespace App\Http\Requests\Communication;

use App\Enums\MediaPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Posting a message into an existing conversation. Authorized against the conversation-
 * level "sendMessage" ability (participants only). A message needs either text or an
 * attachment; mime/size limits mirror the MessageAttachments purpose config.
 */
class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('sendMessage', $this->route('conversation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $purpose = MediaPurpose::MessageAttachments;

        return [
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:50000'],
            'attachment' => [
                'nullable',
                'file',
                'mimetypes:'.implode(',', $purpose->allowedMimes()),
                'max:'.$purpose->maxKb(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = (int) round(MediaPurpose::MessageAttachments->maxKb() / 1024);

        return [
            'body.required_without' => 'Write a message or attach a file.',
            'attachment.mimetypes' => 'That file type isn\'t accepted for a message attachment.',
            'attachment.max' => "Attachments must be {$maxMb}MB or smaller.",
        ];
    }
}
