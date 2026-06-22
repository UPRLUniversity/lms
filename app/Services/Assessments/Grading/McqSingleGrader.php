<?php

namespace App\Services\Assessments\Grading;

use App\Models\Question;

/**
 * Single-answer MCQ and true/false: full points iff the chosen option id is the one
 * correct option, otherwise zero.
 */
class McqSingleGrader implements Grader
{
    public function grade(Question $question, mixed $response, array $layoutRow): GradeResult
    {
        $points = (float) $question->points;
        $correctIds = $question->correctOptionIds();

        // Response is a single option id (string). Anything else is unanswered/invalid.
        $chosen = is_array($response) ? ($response[0] ?? null) : $response;

        $correct = $chosen !== null && $chosen !== '' && in_array((string) $chosen, $correctIds, true);

        return GradeResult::allOrNothing($correct, $points);
    }
}
