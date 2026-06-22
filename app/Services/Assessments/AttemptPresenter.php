<?php

namespace App\Services\Assessments;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Enums\ReviewPolicy;
use App\Models\Attempt;
use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Turns an attempt's frozen layout + the underlying questions into view models for two
 * audiences with very different rules:
 *
 *   takeItems()   → the student, mid-attempt. Renders options/matching/scenario in the
 *                   frozen order and NEVER includes any correctness data (no is_correct,
 *                   no accepted answers, no pair mapping). This is the no-leak guarantee.
 *   reviewItems() → the student, after grading, ONLY when the review policy allows. Adds
 *                   their answer vs the correct answer, per-question score and (optionally)
 *                   the explanation.
 */
class AttemptPresenter
{
    /**
     * Ordered question view models for the take screen. Correctness is never present.
     *
     * @return array<int, array<string, mixed>>
     */
    public function takeItems(Attempt $attempt): array
    {
        $questions = $this->questions($attempt);
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $items = [];
        $number = 0;
        foreach ($attempt->layoutQuestions() as $row) {
            $question = $questions->get((int) $row['id']);
            if (! $question) {
                continue;
            }

            $answer = $answers->get($question->id);
            $items[] = array_merge(
                $this->basePresentation($question, $row),
                [
                    'number' => ++$number,
                    'response' => $answer?->response,
                    'flagged' => (bool) ($answer?->flagged),
                    'answered' => $answer?->isAnswered() ?? false,
                ],
            );
        }

        return $items;
    }

    /**
     * Whether the student may see the per-question breakdown of this attempt right now.
     */
    public function canReview(Attempt $attempt): bool
    {
        if (! $attempt->status->isComplete()) {
            return false;
        }

        return match ($attempt->assessment->review_policy) {
            ReviewPolicy::Never => false,
            ReviewPolicy::Immediately => $attempt->status === AttemptStatus::Graded,
            ReviewPolicy::AfterClose => $attempt->status === AttemptStatus::Graded && $attempt->assessment->hasClosed(),
        };
    }

    /**
     * Per-question review including correct answers — gated: returns [] unless canReview().
     *
     * @return array<int, array<string, mixed>>
     */
    public function reviewItems(Attempt $attempt): array
    {
        if (! $this->canReview($attempt)) {
            return [];
        }

        $showExplanations = (bool) $attempt->assessment->show_explanations;
        $questions = $this->questions($attempt);
        $answers = $attempt->answers()->get()->keyBy('question_id');

        $items = [];
        $number = 0;
        foreach ($attempt->layoutQuestions() as $row) {
            $question = $questions->get((int) $row['id']);
            if (! $question) {
                continue;
            }

            $answer = $answers->get($question->id);

            $items[] = array_merge(
                $this->basePresentation($question, $row),
                [
                    'number' => ++$number,
                    'response' => $answer?->response,
                    'is_correct' => $answer?->is_correct,
                    'points_awarded' => $answer?->points_awarded,
                    'points_possible' => $answer?->points_possible ?? $row['points'] ?? $question->points,
                    'correct' => $this->correctAnswer($question),
                    'feedback' => $answer?->feedback,
                    'explanation' => $showExplanations ? $question->explanation : null,
                ],
            );
        }

        return $items;
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation (shared by take + review) — never includes correctness
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $row  the frozen layout row for this question
     * @return array<string, mixed>
     */
    private function basePresentation(Question $question, array $row): array
    {
        $base = [
            'question_id' => $question->id,
            'type' => $question->type->value,
            'prompt' => $question->prompt,
            'image_url' => $question->image()?->url,
            'points' => $row['points'] ?? $question->points,
        ];

        return array_merge($base, match ($question->type) {
            QuestionType::McqSingle, QuestionType::McqMulti, QuestionType::TrueFalse => [
                'options' => $this->orderedOptions($question, $row['option_order'] ?? []),
                'multiple' => $question->type === QuestionType::McqMulti,
            ],
            QuestionType::FillBlank => [],
            QuestionType::Matching => $this->matchingView($question, $row),
            QuestionType::Essay => [],
            QuestionType::Scenario => [
                'subs' => $this->scenarioSubs($question, $row['sub'] ?? []),
            ],
        });
    }

    /**
     * Options in frozen order, text only — no is_correct.
     *
     * @param  array<int, string>  $order
     * @return array<int, array{id: string, text: string}>
     */
    private function orderedOptions(Question $question, array $order): array
    {
        $byId = collect($question->options())->keyBy('id');

        $ordered = collect($order)
            ->map(fn ($id) => $byId->get($id))
            ->filter()
            ->map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']]);

        // Fall back to natural order if the layout had no order (defensive).
        if ($ordered->isEmpty()) {
            $ordered = collect($question->options())->map(fn ($o) => ['id' => $o['id'], 'text' => $o['text']]);
        }

        return $ordered->values()->all();
    }

    /**
     * Matching lefts (in frozen order) + tokenised rights (no pair mapping leaked).
     *
     * @param  array<string, mixed>  $row
     * @return array{lefts: array<int, array{id: string, text: string}>, rights: array<int, array{token: string, text: string}>}
     */
    private function matchingView(Question $question, array $row): array
    {
        $pairs = collect($question->pairs())->keyBy('id');

        $lefts = collect($row['left_order'] ?? $pairs->keys()->all())
            ->map(fn ($id) => $pairs->get($id))
            ->filter()
            ->map(fn ($p) => ['id' => $p['id'], 'text' => $p['left']])
            ->values()
            ->all();

        $rights = collect($row['right_tokens'] ?? [])
            ->map(fn ($t) => [
                'token' => $t['token'],
                'text' => $pairs->get($t['pair_id'])['right'] ?? '',
            ])
            ->values()
            ->all();

        return ['lefts' => $lefts, 'rights' => $rights];
    }

    /**
     * @param  array<string, mixed>  $subLayouts
     * @return array<int, array<string, mixed>>
     */
    private function scenarioSubs(Question $question, array $subLayouts): array
    {
        $subs = [];
        foreach ($question->subQuestions() as $sub) {
            $subQuestion = $question->makeSubQuestion($sub);
            $row = $subLayouts[$sub['id']] ?? [];

            $subs[] = array_merge(
                ['id' => $sub['id']],
                $this->basePresentation($subQuestion, $row),
            );
        }

        return $subs;
    }

    /*
    |--------------------------------------------------------------------------
    | Correct-answer rendering (review only)
    |--------------------------------------------------------------------------
    */

    /**
     * A human-readable correct answer for review. Only ever reached behind canReview().
     */
    private function correctAnswer(Question $question): mixed
    {
        return match ($question->type) {
            QuestionType::McqSingle, QuestionType::McqMulti, QuestionType::TrueFalse => collect($question->options())
                ->filter(fn ($o) => $o['is_correct'])
                ->pluck('text')
                ->values()
                ->all(),
            QuestionType::FillBlank => $question->acceptedAnswers(),
            QuestionType::Matching => collect($question->pairs())
                ->map(fn ($p) => ['left' => $p['left'], 'right' => $p['right']])
                ->values()
                ->all(),
            QuestionType::Scenario => collect($question->subQuestions())
                ->map(fn ($sub) => [
                    'id' => $sub['id'],
                    'prompt' => $sub['prompt'] ?? '',
                    'correct' => $this->correctAnswer($question->makeSubQuestion($sub)),
                ])
                ->values()
                ->all(),
            QuestionType::Essay => null,
        };
    }

    /**
     * Load the questions referenced by the attempt's layout, keyed by id.
     *
     * @return Collection<int, Question>
     */
    private function questions(Attempt $attempt): Collection
    {
        return Question::withTrashed()
            ->whereIn('id', $attempt->questionIds())
            ->get()
            ->keyBy('id');
    }
}
