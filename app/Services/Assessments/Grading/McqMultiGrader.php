<?php

namespace App\Services\Assessments\Grading;

use App\Models\Question;

/**
 * Multi-select MCQ, graded all-or-nothing: full points only when the chosen option set is
 * exactly the correct set — any wrong option chosen, or any correct option missed, scores
 * zero. (Documented in docs/decisions.md: no partial credit for multi-select.)
 */
class McqMultiGrader implements Grader
{
    public function grade(Question $question, mixed $response, array $layoutRow): GradeResult
    {
        $points = (float) $question->points;

        $chosen = collect(is_array($response) ? $response : [])
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->sort()
            ->values();

        $correct = collect($question->correctOptionIds())
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->sort()
            ->values();

        $isCorrect = $chosen->all() === $correct->all() && $correct->isNotEmpty();

        return GradeResult::allOrNothing($isCorrect, $points);
    }
}
