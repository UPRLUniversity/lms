<?php

namespace App\Services\Assessments\Grading;

use App\Models\Question;

/**
 * Essay: never auto-graded. Returns a manual result carrying only the points possible; an
 * instructor awards the points and writes feedback in the grading queue.
 */
class EssayGrader implements Grader
{
    public function grade(Question $question, mixed $response, array $layoutRow): GradeResult
    {
        return GradeResult::manual((float) $question->points);
    }
}
