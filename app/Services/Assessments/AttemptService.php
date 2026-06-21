<?php

namespace App\Services\Assessments;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\User;
use App\Services\Courses\LearningService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Owns the lifecycle of a student attempt: starting one (resolving + freezing the question
 * layout, enforcing window/attempt limits), autosaving answers against that frozen layout,
 * and submitting (auto-grade → finalise → recalc course progress).
 *
 * The frozen `layout` is the security spine — every saved answer is validated to belong to
 * it, shuffles are fixed for the attempt's life, and the timer is anchored to a
 * server-stored `expires_at` that the client cannot extend.
 */
class AttemptService
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly LearningService $learning,
    ) {}

    /**
     * Begin a new attempt. Enforces — atomically — that the assessment is open, the student
     * has attempts left, and none is already in progress. Builds and freezes the layout.
     *
     * @throws \DomainException when starting is not permitted (window/limit/in-progress)
     */
    public function startAttempt(Assessment $assessment, User $user): Attempt
    {
        return DB::transaction(function () use ($assessment, $user) {
            // Re-check inside the transaction with a row lock on prior attempts, so two
            // racing requests can't both slip past max_attempts.
            $used = $assessment->attempts()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->get();

            if (! $assessment->isPublished() || ! $assessment->withinWindow()) {
                throw new \DomainException('This assessment is not open.');
            }

            if ($used->firstWhere('status', AttemptStatus::InProgress) !== null) {
                throw new \DomainException('You already have an attempt in progress.');
            }

            if ($assessment->max_attempts !== null && $used->count() >= $assessment->max_attempts) {
                throw new \DomainException('You have used all your attempts.');
            }

            $questions = $this->resolveQuestions($assessment);

            if ($questions->isEmpty()) {
                throw new \DomainException('This assessment has no questions.');
            }

            [$layout, $maxScore] = $this->buildLayout($assessment, $questions);

            $startedAt = now();

            return $assessment->attempts()->create([
                'user_id' => $user->id,
                'attempt_number' => ($used->max('attempt_number') ?? 0) + 1,
                'started_at' => $startedAt,
                'expires_at' => $assessment->isTimed()
                    ? $startedAt->copy()->addMinutes($assessment->time_limit_minutes)
                    : null,
                'max_score' => $maxScore,
                'status' => AttemptStatus::InProgress->value,
                'layout' => $layout,
            ]);
        });
    }

    /**
     * If a timed attempt's clock has run out, auto-submit it. Called on every load of the
     * take/result screens so a walked-away student is graded at zero-fill. Returns the
     * attempt (possibly now submitted/graded).
     */
    public function ensureFresh(Attempt $attempt): Attempt
    {
        if ($attempt->isInProgress() && $attempt->isExpired()) {
            return $this->submit($attempt);
        }

        return $attempt;
    }

    /**
     * Autosave one answer. Validates the question belongs to the frozen layout (tamper
     * rejection) and that the attempt is still open. Upsert by (attempt, question).
     *
     * @throws \DomainException when the attempt is closed or the question isn't in the layout
     */
    public function saveAnswer(Attempt $attempt, int $questionId, mixed $response, ?bool $flagged = null): AttemptAnswer
    {
        if (! $attempt->isInProgress() || $attempt->isExpired()) {
            throw new \DomainException('This attempt can no longer be edited.');
        }

        if (! $attempt->includesQuestion($questionId)) {
            throw new \DomainException('That question is not part of this attempt.');
        }

        $answer = $attempt->answers()->firstOrNew(['question_id' => $questionId]);

        if ($response !== null) {
            $answer->response = $this->sanitiseResponse($response);
        }

        if ($flagged !== null) {
            $answer->flagged = $flagged;
        }

        $answer->save();

        return $answer;
    }

    /**
     * Submit an attempt: backfill answer rows for unanswered questions, auto-grade the
     * objective parts, then finalise (→ graded, or → submitted if essays await a human) and
     * recalculate the student's course progress.
     */
    public function submit(Attempt $attempt): Attempt
    {
        if (! $attempt->isInProgress()) {
            return $attempt;
        }

        DB::transaction(function () use ($attempt) {
            $attempt->forceFill([
                'submitted_at' => now(),
                'status' => AttemptStatus::Submitted->value,
            ])->save();

            $this->backfillAnswers($attempt);
            $this->grading->autoGrade($attempt);
            $this->grading->finalize($attempt);
        });

        $this->recalculateProgress($attempt);

        return $attempt->refresh();
    }

    /**
     * Finalise an attempt after manual grading and recalc progress. Used by the grading
     * queue once an instructor settles the last essay. Returns true if it became graded.
     */
    public function finalizeAfterGrading(Attempt $attempt): bool
    {
        $became = $this->grading->finalize($attempt);

        if ($became) {
            $this->recalculateProgress($attempt);
        }

        return $became;
    }

    /*
    |--------------------------------------------------------------------------
    | Layout construction
    |--------------------------------------------------------------------------
    */

    /**
     * The questions for an attempt: the fixed ordered list, or a fresh random draw obeying
     * the pool rules. Shuffle_questions reorders the whole set.
     *
     * @return Collection<int, Question>
     */
    public function resolveQuestions(Assessment $assessment): Collection
    {
        if ($assessment->isPooled()) {
            $picked = new Collection;
            $seen = [];

            foreach ($assessment->poolRules as $rule) {
                $drawn = Question::query()
                    ->where('category_id', $rule->category_id)
                    ->when($rule->difficulty, fn ($q) => $q->where('difficulty', $rule->difficulty->value))
                    ->whereNotIn('id', $seen)
                    ->inRandomOrder()
                    ->limit($rule->count)
                    ->get();

                foreach ($drawn as $question) {
                    $seen[] = $question->id;
                    $picked->push($question);
                }
            }

            return $assessment->shuffle_questions ? $picked->shuffle()->values() : $picked->values();
        }

        $questions = $assessment->questions()->get();

        return $assessment->shuffle_questions ? $questions->shuffle()->values() : $questions->values();
    }

    /**
     * Build the frozen layout + total max score from an ordered question collection.
     *
     * @param  Collection<int, Question>  $questions
     * @return array{0: array{questions: array<int, array<string, mixed>>}, 1: float}
     */
    private function buildLayout(Assessment $assessment, Collection $questions): array
    {
        $shuffleOptions = (bool) $assessment->shuffle_options;
        $rows = [];
        $maxScore = 0.0;

        foreach ($questions as $question) {
            $points = (float) ($question->pivot->points_override ?? $question->points);
            $maxScore += $points;
            $rows[] = $this->layoutRow($question, $points, $shuffleOptions);
        }

        return [['questions' => $rows], round($maxScore, 2)];
    }

    /**
     * One question's frozen presentation: its id, frozen points, and the shuffled-but-fixed
     * order of its options / matching tokens / scenario sub-parts.
     *
     * @return array<string, mixed>
     */
    private function layoutRow(Question $question, float $points, bool $shuffleOptions): array
    {
        $row = ['id' => $question->id, 'points' => $points];

        return array_merge($row, $this->presentation($question, $shuffleOptions));
    }

    /**
     * The per-type presentation fragment (no points — used for both top-level questions and
     * scenario sub-questions).
     *
     * @return array<string, mixed>
     */
    private function presentation(Question $question, bool $shuffleOptions): array
    {
        return match ($question->type) {
            QuestionType::McqSingle, QuestionType::McqMulti, QuestionType::TrueFalse => [
                'option_order' => $this->order(array_column($question->options(), 'id'), $shuffleOptions),
            ],
            QuestionType::Matching => $this->matchingPresentation($question, $shuffleOptions),
            QuestionType::Scenario => [
                'sub' => collect($question->subQuestions())
                    ->mapWithKeys(fn (array $sub) => [
                        $sub['id'] => $this->presentation($question->makeSubQuestion($sub), $shuffleOptions),
                    ])
                    ->all(),
            ],
            default => [], // fill_blank, essay carry no presentation order
        };
    }

    /**
     * Lefts in (optionally shuffled) order; rights always tokenised + shuffled so the correct
     * mapping can't be inferred from the DOM.
     *
     * @return array{left_order: array<int, string>, right_tokens: array<int, array{token: string, pair_id: string}>}
     */
    private function matchingPresentation(Question $question, bool $shuffleOptions): array
    {
        $pairs = $question->pairs();

        $leftOrder = collect($pairs)->pluck('id');
        if ($shuffleOptions) {
            $leftOrder = $leftOrder->shuffle();
        }

        $tokens = collect($pairs)
            ->map(fn (array $p) => ['token' => 'r_'.Str::lower(Str::random(10)), 'pair_id' => $p['id']])
            ->shuffle();

        return [
            'left_order' => $leftOrder->values()->all(),
            'right_tokens' => $tokens->values()->all(),
        ];
    }

    /**
     * @param  array<int, string>  $ids
     * @return array<int, string>
     */
    private function order(array $ids, bool $shuffle): array
    {
        return $shuffle ? collect($ids)->shuffle()->values()->all() : array_values($ids);
    }

    /*
    |--------------------------------------------------------------------------
    | Submission helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure every question in the layout has an answer row, so unanswered questions score
     * zero (objective) / await grading (essay) and appear in review.
     */
    private function backfillAnswers(Attempt $attempt): void
    {
        $existing = $attempt->answers()->pluck('question_id')->all();

        foreach ($attempt->questionIds() as $questionId) {
            if (! in_array($questionId, $existing, true)) {
                $attempt->answers()->create([
                    'question_id' => $questionId,
                    'response' => null,
                ]);
            }
        }
    }

    /**
     * Push the attempt outcome into course progress: a passed required assessment counts
     * toward the course percentage like a completed lesson.
     */
    private function recalculateProgress(Attempt $attempt): void
    {
        $attempt->loadMissing('assessment.course');
        $course = $attempt->assessment?->course;

        if ($course) {
            $this->learning->recalculate($attempt->user ?? $attempt->user()->first(), $course);
        }
    }

    /**
     * Light structural sanitising of a raw response so only scalar/array shapes land in the
     * JSON column (defence in depth; the real grading validation is the frozen layout).
     */
    private function sanitiseResponse(mixed $response): mixed
    {
        if (is_array($response)) {
            return $response;
        }

        return is_scalar($response) ? $response : null;
    }
}
