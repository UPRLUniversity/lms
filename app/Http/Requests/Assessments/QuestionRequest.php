<?php

namespace App\Http\Requests\Assessments;

use App\Enums\QuestionDifficulty;
use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validation for authoring a question (store + update). The structural shape of `payload`
 * is checked per type here; QuestionBankService normalises ids and trims afterwards.
 */
class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageCurriculum', $this->route('course'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', QuestionType::values())],
            'difficulty' => ['required', 'string', 'in:'.implode(',', QuestionDifficulty::values())],
            'category_id' => ['nullable', 'integer', 'exists:question_categories,id'],
            'prompt' => ['required', 'string', 'max:20000'],
            'explanation' => ['nullable', 'string', 'max:20000'],
            'points' => ['required', 'numeric', 'min:0', 'max:1000'],

            'payload' => ['nullable', 'array'],
            // Option types
            'payload.options' => ['array'],
            'payload.options.*.text' => ['required_with:payload.options', 'string', 'max:2000'],
            'payload.options.*.is_correct' => ['boolean'],
            // True/false
            'payload.answer' => ['nullable', 'boolean'],
            // Fill blank
            'payload.accepted' => ['array'],
            'payload.accepted.*' => ['string', 'max:1000'],
            'payload.case_insensitive' => ['boolean'],
            // Matching
            'payload.pairs' => ['array'],
            'payload.pairs.*.left' => ['required_with:payload.pairs', 'string', 'max:1000'],
            'payload.pairs.*.right' => ['required_with:payload.pairs', 'string', 'max:1000'],
            // Essay
            'payload.guidance' => ['nullable', 'string', 'max:20000'],
            // Scenario
            'payload.sub_questions' => ['array'],
            'payload.sub_questions.*.type' => ['required_with:payload.sub_questions', 'string', 'in:'.implode(',', array_map(fn ($t) => $t->value, QuestionType::subQuestionTypes()))],
            'payload.sub_questions.*.prompt' => ['required_with:payload.sub_questions', 'string', 'max:20000'],
            'payload.sub_questions.*.points' => ['required_with:payload.sub_questions', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * Type-specific minimums the flat rules can't express (e.g. an MCQ needs a correct
     * option; a fill-blank needs an accepted answer).
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $type = QuestionType::tryFrom((string) $this->input('type'));
            $payload = (array) $this->input('payload', []);

            if (in_array($type, [QuestionType::McqSingle, QuestionType::McqMulti], true)) {
                $options = $payload['options'] ?? [];
                if (count($options) < 2) {
                    $validator->errors()->add('payload.options', 'Add at least two options.');
                }
                $correct = array_filter($options, fn ($o) => filter_var($o['is_correct'] ?? false, FILTER_VALIDATE_BOOL));
                if (count($correct) < 1) {
                    $validator->errors()->add('payload.options', 'Mark at least one option correct.');
                }
                if ($type === QuestionType::McqSingle && count($correct) > 1) {
                    $validator->errors()->add('payload.options', 'A single-answer question can have only one correct option.');
                }
            }

            if ($type === QuestionType::FillBlank) {
                $accepted = array_filter($payload['accepted'] ?? [], fn ($a) => trim((string) $a) !== '');
                if (count($accepted) < 1) {
                    $validator->errors()->add('payload.accepted', 'Add at least one accepted answer.');
                }
            }

            if ($type === QuestionType::Matching && count($payload['pairs'] ?? []) < 2) {
                $validator->errors()->add('payload.pairs', 'Add at least two matching pairs.');
            }

            if ($type === QuestionType::Scenario && count($payload['sub_questions'] ?? []) < 1) {
                $validator->errors()->add('payload.sub_questions', 'Add at least one sub-question.');
            }
        }];
    }
}
