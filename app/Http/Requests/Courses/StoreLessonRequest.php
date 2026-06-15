<?php

namespace App\Http\Requests\Courses;

use App\Http\Requests\Courses\Concerns\ValidatesLessonPayload;
use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
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
        return $this->lessonRules(fileAlreadyStored: false);
    }
}
