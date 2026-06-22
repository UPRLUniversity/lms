<?php

namespace App\Services\Assessments\Grading;

use App\Models\Question;
use App\Services\Assessments\GradingService;

/**
 * Scenario: a container graded by delegating each sub-question to its own grader.
 * - All sub-questions objective → auto-graded; scenario points = Σ sub awards.
 * - Any essay sub-question → the whole scenario is manual (an instructor settles it),
 *   with the objective sub-score available as a hint via GradingService.
 */
class ScenarioGrader implements Grader
{
    public function __construct(private readonly GradingService $grading) {}

    public function grade(Question $question, mixed $response, array $layoutRow): GradeResult
    {
        $response = is_array($response) ? $response : [];
        $subLayouts = $layoutRow['sub'] ?? [];

        $possible = 0.0;
        $awarded = 0.0;
        $hasManual = false;
        $allCorrect = true;

        foreach ($question->subQuestions() as $sub) {
            $subQuestion = $question->makeSubQuestion($sub);
            $possible += (float) $subQuestion->points;

            $result = $this->grading->grade(
                $subQuestion,
                $response[$sub['id']] ?? null,
                $subLayouts[$sub['id']] ?? [],
            );

            if ($result->manual) {
                $hasManual = true;

                continue;
            }

            $awarded += $result->pointsAwarded ?? 0.0;
            $allCorrect = $allCorrect && (bool) $result->isCorrect;
        }

        if ($hasManual) {
            return GradeResult::manual($possible);
        }

        return GradeResult::auto($awarded, $possible, $allCorrect);
    }
}
