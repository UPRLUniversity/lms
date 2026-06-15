<?php

namespace App\Http\Requests\Courses;

use App\Enums\MediaPurpose;
use App\Http\Requests\Courses\Concerns\ValidatesLessonPayload;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonRequest extends FormRequest
{
    use ValidatesLessonPayload;

    public function authorize(): bool
    {
        return $this->user()->can('manageCurriculum', $this->route('course'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A file-type lesson that already has its file may be saved without
        // re-uploading; a new file replaces the old one.
        $hasFile = $this->route('lesson')?->firstMediaFor(MediaPurpose::LessonMedia) !== null;

        return $this->lessonRules(fileAlreadyStored: $hasFile);
    }
}
