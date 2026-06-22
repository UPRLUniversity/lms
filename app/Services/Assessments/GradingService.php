<?php

namespace App\Services\Assessments;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\Question;
use App\Services\Assessments\Grading\EssayGrader;
use App\Services\Assessments\Grading\FillBlankGrader;
use App\Services\Assessments\Grading\Grader;
use App\Services\Assessments\Grading\GradeResult;
use App\Services\Assessments\Grading\MatchingGrader;
use App\Services\Assessments\Grading\McqMultiGrader;
use App\Services\Assessments\Grading\McqSingleGrader;
use App\Services\Assessments\Grading\ScenarioGrader;

/**
 * The grading core: maps a question type to its grader strategy, auto-grades the objective
 * answers on an attempt, and finalises an attempt's score/pass-fail once nothing is left
 * for a human. The per-type strategies are pure (see Grading/*Grader), which is what makes
 * the whole matrix unit-testable.
 */
class GradingService
{
    /**
     * Resolve the grader for a question type. Single-answer MCQ and true/false share one
     * strategy; scenario delegates back to this service for its sub-questions.
     */
    public function graderFor(QuestionType $type): Grader
    {
        return match ($type) {
            QuestionType::McqSingle, QuestionType::TrueFalse => new McqSingleGrader,
            QuestionType::McqMulti => new McqMultiGrader,
            QuestionType::FillBlank => new FillBlankGrader,
            QuestionType::Matching => new MatchingGrader,
            QuestionType::Essay => new EssayGrader,
            QuestionType::Scenario => new ScenarioGrader($this),
        };
    }

    /**
     * Grade one answer in isolation.
     *
     * @param  array<string, mixed>  $layoutRow
     */
    public function grade(Question $question, mixed $response, array $layoutRow = []): GradeResult
    {
        return $this->graderFor($question->type)->grade($question, $response, $layoutRow);
    }

    /**
     * Auto-grade every answer on an attempt: objective answers get their points + is_correct
     * settled now; manual (essay / scenario-with-essay) answers keep null points_awarded and
     * fall to the grading queue. points_possible is always recorded so totals stay stable.
     */
    public function autoGrade(Attempt $attempt): void
    {
        $attempt->loadMissing('answers.question');

        foreach ($attempt->answers as $answer) {
            $question = $answer->question;
            if (! $question) {
                continue;
            }

            $layoutRow = $attempt->layoutFor($question->id) ?? [];

            // Honour the points frozen into the layout (a fixed-mode points_override), so
            // grading matches exactly what the student was shown.
            if (isset($layoutRow['points'])) {
                $question->points = $layoutRow['points'];
            }

            $result = $this->grade($question, $answer->response, $layoutRow);

            $answer->points_possible = $result->pointsPossible;

            if ($result->manual) {
                $answer->points_awarded = null;
                $answer->is_correct = null;
                $answer->graded_at = null;
            } else {
                $answer->points_awarded = $result->pointsAwarded;
                $answer->is_correct = $result->isCorrect;
                $answer->graded_at = now();
            }

            $answer->save();
        }
    }

    /**
     * Settle an attempt's final score. While any answer still awaits a human the attempt
     * stays 'submitted'; once all are graded it flips to 'graded' with score/%/pass-fail.
     * Idempotent. Returns true when it transitioned to graded on this call.
     */
    public function finalize(Attempt $attempt): bool
    {
        $attempt->loadMissing(['answers', 'assessment']);

        if ($attempt->answers->whereNull('points_awarded')->isNotEmpty()) {
            if ($attempt->status !== AttemptStatus::Submitted) {
                $attempt->forceFill(['status' => AttemptStatus::Submitted->value])->save();
            }

            return false;
        }

        $score = (float) $attempt->answers->sum(fn ($a) => (float) $a->points_awarded);
        $max = (float) ($attempt->max_score ?? $attempt->answers->sum(fn ($a) => (float) $a->points_possible));
        $percentage = $max > 0 ? (int) round($score / $max * 100) : 0;
        $passed = $percentage >= (int) $attempt->assessment->passing_score;

        $attempt->forceFill([
            'score' => round($score, 2),
            'max_score' => round($max, 2),
            'percentage' => $percentage,
            'passed' => $passed,
            'status' => AttemptStatus::Graded->value,
        ])->save();

        return true;
    }

    /**
     * The objective auto-score of a scenario answer (ignoring its essay sub-parts) — shown
     * to the instructor as a hint when grading a mixed scenario. Returns [awarded, possible].
     *
     * @param  array<string, mixed>  $layoutRow
     * @return array{0: float, 1: float}
     */
    public function scenarioObjectiveSubtotal(Question $question, mixed $response, array $layoutRow): array
    {
        $response = is_array($response) ? $response : [];
        $subLayouts = $layoutRow['sub'] ?? [];
        $awarded = 0.0;
        $possible = 0.0;

        foreach ($question->subQuestions() as $sub) {
            if (($sub['type'] ?? null) === QuestionType::Essay->value) {
                continue;
            }

            $subQuestion = $question->makeSubQuestion($sub);
            $result = $this->grade($subQuestion, $response[$sub['id']] ?? null, $subLayouts[$sub['id']] ?? []);
            $awarded += $result->pointsAwarded ?? 0.0;
            $possible += $result->pointsPossible;
        }

        return [round($awarded, 2), round($possible, 2)];
    }
}
