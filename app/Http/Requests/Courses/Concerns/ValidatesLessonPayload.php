<?php

namespace App\Http\Requests\Courses\Concerns;

use App\Enums\LessonType;
use App\Services\Courses\VideoEmbedService;
use Illuminate\Validation\Rule;

/**
 * Shared conditional rules for authoring a lesson. The payload that's required
 * depends on the chosen type (and, for video, whether it's an embed or an upload),
 * so both the store and update requests build their rules from here.
 */
trait ValidatesLessonPayload
{
    /**
     * @return array<string, mixed>
     */
    protected function lessonRules(bool $fileAlreadyStored = false): array
    {
        $type = $this->input('type');
        $videoSource = $this->input('video_source', 'embed');
        $lessonMediaMaxKb = (int) config('media.purposes.lesson_media.max_kb', 25600);

        $rules = [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(LessonType::values())],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_free_preview' => ['boolean'],
            'content_text' => ['nullable', 'string'],
            'video_source' => ['nullable', Rule::in(['embed', 'upload'])],
            'video_url' => ['nullable', 'string', 'max:2048'],
            'external_url' => ['nullable', 'string', 'url', 'max:2048'],
            'file' => ['nullable', 'file', "max:{$lessonMediaMaxKb}"],
        ];

        // Type-specific tightening.
        if ($type === LessonType::ExternalLink->value) {
            $rules['external_url'] = ['required', 'string', 'url', 'max:2048'];
        }

        if ($type === LessonType::Video->value) {
            if ($videoSource === 'upload') {
                // An uploaded video needs a file unless one is already stored.
                $rules['file'] = [$fileAlreadyStored ? 'nullable' : 'required', 'file', "max:{$lessonMediaMaxKb}"];
            } else {
                $rules['video_url'] = [
                    'required', 'string', 'max:2048',
                    function ($attribute, $value, $fail) {
                        if (! app(VideoEmbedService::class)->isValid((string) $value)) {
                            $fail('Enter a valid YouTube or Vimeo video URL.');
                        }
                    },
                ];
            }
        }

        if (in_array($type, [LessonType::Pdf->value, LessonType::Document->value, LessonType::Audio->value], true)) {
            $rules['file'] = [$fileAlreadyStored ? 'nullable' : 'required', 'file', "max:{$lessonMediaMaxKb}"];
        }

        return $rules;
    }
}
