<?php

namespace App\Services\Assessments\Grading;

use App\Models\Question;

/**
 * Matching, graded proportionally: each correctly matched pair earns its share of the
 * question's points — `points × (correctPairs / totalPairs)`, rounded to 2 dp. (Documented
 * in docs/decisions.md.)
 *
 * The student's response maps each left pair id to an opaque right *token*. The frozen
 * layout row resolves token → pair_id, so the correct mapping can't be read off the DOM
 * (the rights are shuffled and tokenised). When no layout is supplied (pure unit grading),
 * tokens are taken to be the pair ids themselves.
 */
class MatchingGrader implements Grader
{
    public function grade(Question $question, mixed $response, array $layoutRow): GradeResult
    {
        $points = (float) $question->points;
        $pairs = $question->pairs();
        $total = count($pairs);

        if ($total === 0) {
            return GradeResult::auto(0, 0, true);
        }

        $tokenToPair = $this->tokenMap($layoutRow);
        $response = is_array($response) ? $response : [];

        $correct = 0;
        foreach ($pairs as $pair) {
            $chosenToken = $response[$pair['id']] ?? null;
            if ($chosenToken === null) {
                continue;
            }

            $resolved = $tokenToPair[$chosenToken] ?? $chosenToken;
            if ((string) $resolved === $pair['id']) {
                $correct++;
            }
        }

        $awarded = $points * ($correct / $total);

        return GradeResult::auto($awarded, $points, $correct === $total);
    }

    /**
     * token => pair_id from the frozen layout row's right_tokens.
     *
     * @return array<string, string>
     */
    private function tokenMap(array $layoutRow): array
    {
        $map = [];
        foreach ($layoutRow['right_tokens'] ?? [] as $entry) {
            if (isset($entry['token'], $entry['pair_id'])) {
                $map[(string) $entry['token']] = (string) $entry['pair_id'];
            }
        }

        return $map;
    }
}
