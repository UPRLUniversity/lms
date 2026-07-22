<?php

namespace App\Http\Requests\Assignments;

use App\Enums\MediaPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * A student handing in a submission version. Mime/size limits come straight from the
 * Submissions purpose config, so the form can never accept more than the storage
 * service will (which re-validates regardless).
 */
class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('submit', $this->route('assignment'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $purpose = MediaPurpose::Submissions;

        return [
            'body' => ['nullable', 'string', 'max:200000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => [
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
        $maxMb = (int) round(MediaPurpose::Submissions->maxKb() / 1024);

        return [
            'files.max' => 'You can attach up to 5 files per submission.',
            'files.*.mimetypes' => 'That file type isn\'t accepted — upload a PDF, Word document, ZIP, plain text or image file.',
            'files.*.max' => "Each file must be {$maxMb}MB or smaller.",
        ];
    }
}
