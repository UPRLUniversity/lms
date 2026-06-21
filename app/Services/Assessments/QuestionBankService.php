<?php

namespace App\Services\Assessments;

use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Authoring side of the question bank: create/update with per-type payload normalisation,
 * duplicate, safe-delete (blocked while a published assessment depends on the question),
 * and import from another of the instructor's courses.
 */
class QuestionBankService
{
    /**
     * @param  array<string, mixed>  $data  validated: type, difficulty, prompt, explanation,
     *                                      points, category_id, course_id, payload
     */
    public function create(array $data, User $author): Question
    {
        $type = $data['type'] instanceof QuestionType ? $data['type'] : QuestionType::from($data['type']);

        return Question::create([
            'category_id' => $data['category_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'created_by' => $author->id,
            'type' => $type->value,
            'difficulty' => $data['difficulty'] ?? 'medium',
            'prompt' => $data['prompt'] ?? '',
            'explanation' => $data['explanation'] ?? null,
            'points' => $data['points'] ?? 1,
            'payload' => $this->normalisePayload($type, $data['payload'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Question $question, array $data): Question
    {
        $type = isset($data['type'])
            ? ($data['type'] instanceof QuestionType ? $data['type'] : QuestionType::from($data['type']))
            : $question->type;

        $question->fill([
            'category_id' => $data['category_id'] ?? $question->category_id,
            'type' => $type->value,
            'difficulty' => $data['difficulty'] ?? $question->difficulty->value,
            'prompt' => $data['prompt'] ?? $question->prompt,
            'explanation' => $data['explanation'] ?? $question->explanation,
            'points' => $data['points'] ?? $question->points,
        ]);

        if (array_key_exists('payload', $data)) {
            $question->payload = $this->normalisePayload($type, $data['payload'] ?? []);
        }

        $question->save();

        return $question;
    }

    /**
     * Clone a question into the same course/category, with a "(copy)" marker on the prompt.
     */
    public function duplicate(Question $question, User $author): Question
    {
        return Question::create([
            'category_id' => $question->category_id,
            'course_id' => $question->course_id,
            'created_by' => $author->id,
            'type' => $question->type->value,
            'difficulty' => $question->difficulty->value,
            'prompt' => $this->withCopyMarker($question->getRawOriginal('prompt') ?? $question->prompt),
            'explanation' => $question->explanation,
            'points' => $question->points,
            'payload' => $question->payload,
        ]);
    }

    /**
     * Safe-delete: refuse while a *published* assessment references the question (so live
     * exams keep their items); otherwise soft-delete it out of the bank.
     *
     * @throws \DomainException when the question is in use by a published assessment
     */
    public function delete(Question $question): void
    {
        if ($question->usedByPublishedAssessment()) {
            throw new \DomainException('This question is used by a published assessment. Duplicate it to make changes, or unpublish the assessment first.');
        }

        $question->delete();
    }

    /**
     * Copy a set of questions from one course into another (both the instructor's). Returns
     * the number imported. Categories are matched by name in the target, creating any missing.
     *
     * @param  array<int, int>  $questionIds
     */
    public function importFromCourse(Course $source, Course $target, array $questionIds, User $author): int
    {
        $questions = Question::query()
            ->where('course_id', $source->id)
            ->whereIn('id', $questionIds)
            ->get();

        $count = 0;
        foreach ($questions as $question) {
            $categoryId = $this->mirrorCategory($question, $target, $author);

            Question::create([
                'category_id' => $categoryId,
                'course_id' => $target->id,
                'created_by' => $author->id,
                'type' => $question->type->value,
                'difficulty' => $question->difficulty->value,
                'prompt' => $question->getRawOriginal('prompt'),
                'explanation' => $question->getRawOriginal('explanation'),
                'points' => $question->points,
                'payload' => $question->payload,
            ]);
            $count++;
        }

        return $count;
    }

    /*
    |--------------------------------------------------------------------------
    | Payload normalisation — guarantee stable ids the layout/grader rely on
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalisePayload(QuestionType $type, array $payload): array
    {
        return match ($type) {
            QuestionType::McqSingle, QuestionType::McqMulti => [
                'options' => $this->normaliseOptions($payload['options'] ?? []),
            ],
            QuestionType::TrueFalse => [
                'options' => [
                    ['id' => 'true', 'text' => 'True', 'is_correct' => (bool) ($payload['answer'] ?? true)],
                    ['id' => 'false', 'text' => 'False', 'is_correct' => ! (bool) ($payload['answer'] ?? true)],
                ],
            ],
            QuestionType::FillBlank => [
                'accepted' => array_values(array_filter(array_map(
                    fn ($a) => trim((string) $a),
                    $payload['accepted'] ?? [],
                ), fn ($a) => $a !== '')),
                'case_insensitive' => (bool) ($payload['case_insensitive'] ?? true),
            ],
            QuestionType::Matching => [
                'pairs' => $this->normalisePairs($payload['pairs'] ?? []),
            ],
            QuestionType::Essay => [
                'guidance' => $payload['guidance'] ?? null,
            ],
            QuestionType::Scenario => [
                'sub_questions' => $this->normaliseSubQuestions($payload['sub_questions'] ?? []),
            ],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array{id: string, text: string, is_correct: bool}>
     */
    private function normaliseOptions(array $options): array
    {
        return array_values(array_map(fn (array $o) => [
            'id' => (string) ($o['id'] ?? 'o_'.Str::lower(Str::random(8))),
            'text' => (string) ($o['text'] ?? ''),
            'is_correct' => (bool) ($o['is_correct'] ?? false),
        ], $options));
    }

    /**
     * @param  array<int, array<string, mixed>>  $pairs
     * @return array<int, array{id: string, left: string, right: string}>
     */
    private function normalisePairs(array $pairs): array
    {
        return array_values(array_map(fn (array $p) => [
            'id' => (string) ($p['id'] ?? 'p_'.Str::lower(Str::random(8))),
            'left' => (string) ($p['left'] ?? ''),
            'right' => (string) ($p['right'] ?? ''),
        ], $pairs));
    }

    /**
     * @param  array<int, array<string, mixed>>  $subs
     * @return array<int, array<string, mixed>>
     */
    private function normaliseSubQuestions(array $subs): array
    {
        return array_values(array_map(function (array $sub) {
            $type = QuestionType::from($sub['type'] ?? QuestionType::McqSingle->value);

            return [
                'id' => (string) ($sub['id'] ?? 's_'.Str::lower(Str::random(8))),
                'type' => $type->value,
                // Sub-prompts/explanations are rich HTML inside the parent's payload JSON,
                // so they bypass the RichHtml cast — sanitise them here on the same allow-list.
                'prompt' => $this->cleanRich($sub['prompt'] ?? ''),
                'points' => (float) ($sub['points'] ?? 1),
                'explanation' => isset($sub['explanation']) ? $this->cleanRich($sub['explanation']) : null,
                'payload' => $this->normalisePayload($type, $sub['payload'] ?? []),
            ];
        }, $subs));
    }

    private function withCopyMarker(string $prompt): string
    {
        return $prompt.' <em>(copy)</em>';
    }

    /**
     * Sanitise a rich-HTML fragment on the same allow-list the RichHtml cast uses.
     */
    private function cleanRich(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value === '' ? '' : null;
        }

        return Purifier::clean($value, 'rich');
    }

    /**
     * Find-or-create the target course's category matching the source question's category
     * name, so an import keeps its grouping.
     */
    private function mirrorCategory(Question $question, Course $target, User $author): ?int
    {
        $name = $question->category?->name;
        if (! $name) {
            return null;
        }

        return $target->questionCategories()->firstOrCreate(
            ['name' => $name],
            ['created_by' => $author->id],
        )->id;
    }
}
