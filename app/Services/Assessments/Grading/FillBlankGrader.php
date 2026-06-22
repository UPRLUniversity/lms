<?php

namespace App\Services\Assessments\Grading;

use App\Models\Question;

/**
 * Fill-in-the-blank: the trimmed response must match one of the accepted answers. When the
 * question's case_insensitive flag is set, the comparison folds case (and is the default).
 * All-or-nothing for the single blank.
 */
class FillBlankGrader implements Grader
{
    public function grade(Question $question, mixed $response, array $layoutRow): GradeResult
    {
        $points = (float) $question->points;
        $caseInsensitive = $question->isCaseInsensitive();

        $given = $this->normalise(is_array($response) ? ($response[0] ?? '') : (string) $response, $caseInsensitive);

        if ($given === '') {
            return GradeResult::allOrNothing(false, $points);
        }

        foreach ($question->acceptedAnswers() as $accepted) {
            if ($this->normalise($accepted, $caseInsensitive) === $given) {
                return GradeResult::allOrNothing(true, $points);
            }
        }

        return GradeResult::allOrNothing(false, $points);
    }

    private function normalise(string $value, bool $caseInsensitive): string
    {
        $value = trim($value);

        return $caseInsensitive ? mb_strtolower($value) : $value;
    }
}
