<?php

namespace App\Services\Assessments\Grading;

use App\Models\Question;

/**
 * A grading strategy for one question type. Pure: given a question, the student's raw
 * response and the question's frozen layout row (needed to resolve shuffled matching
 * tokens), it returns a GradeResult. No DB, no side effects — so the whole grading matrix
 * is unit-testable in isolation.
 */
interface Grader
{
    /**
     * @param  mixed  $response  the student's raw response (shape per type)
     * @param  array<string, mixed>  $layoutRow  this question's frozen layout row
     */
    public function grade(Question $question, mixed $response, array $layoutRow): GradeResult;
}
