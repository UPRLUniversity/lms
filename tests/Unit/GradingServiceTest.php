<?php

namespace Tests\Unit;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Services\Assessments\GradingService;
use PHPUnit\Framework\TestCase;

/**
 * The auto-grading matrix, in isolation: every objective type, the all-or-nothing /
 * proportional rules, the fill-blank case flag, and the manual fall-through for essays
 * and mixed scenarios. Pure — in-memory questions, no database.
 */
class GradingServiceTest extends TestCase
{
    private GradingService $grading;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grading = new GradingService;
    }

    private function question(QuestionType $type, array $payload, float $points): Question
    {
        return (new Question)->forceFill([
            'type' => $type,
            'points' => $points,
            'payload' => $payload,
        ]);
    }

    public function test_mcq_single_is_all_or_nothing(): void
    {
        $q = $this->question(QuestionType::McqSingle, [
            'options' => [
                ['id' => 'o1', 'text' => 'A', 'is_correct' => true],
                ['id' => 'o2', 'text' => 'B', 'is_correct' => false],
            ],
        ], 2);

        $this->assertSame(2.0, $this->grading->grade($q, 'o1')->pointsAwarded);
        $this->assertTrue($this->grading->grade($q, 'o1')->isCorrect);
        $this->assertSame(0.0, $this->grading->grade($q, 'o2')->pointsAwarded);
        $this->assertSame(0.0, $this->grading->grade($q, null)->pointsAwarded);
    }

    public function test_true_false_uses_the_single_grader(): void
    {
        $q = $this->question(QuestionType::TrueFalse, [
            'options' => [
                ['id' => 'true', 'text' => 'True', 'is_correct' => true],
                ['id' => 'false', 'text' => 'False', 'is_correct' => false],
            ],
        ], 1);

        $this->assertTrue($this->grading->grade($q, 'true')->isCorrect);
        $this->assertFalse($this->grading->grade($q, 'false')->isCorrect);
    }

    /**
     * Multi-select: full marks only for the exact correct set — any wrong or missing
     * option scores zero (no partial credit).
     */
    public function test_mcq_multi_is_all_or_nothing(): void
    {
        $q = $this->question(QuestionType::McqMulti, [
            'options' => [
                ['id' => 'o1', 'text' => 'A', 'is_correct' => true],
                ['id' => 'o2', 'text' => 'B', 'is_correct' => true],
                ['id' => 'o3', 'text' => 'C', 'is_correct' => false],
            ],
        ], 3);

        $this->assertSame(3.0, $this->grading->grade($q, ['o1', 'o2'])->pointsAwarded);
        $this->assertSame(3.0, $this->grading->grade($q, ['o2', 'o1'])->pointsAwarded, 'order-insensitive');
        $this->assertSame(0.0, $this->grading->grade($q, ['o1'])->pointsAwarded, 'missing a correct option');
        $this->assertSame(0.0, $this->grading->grade($q, ['o1', 'o2', 'o3'])->pointsAwarded, 'one wrong option chosen');
        $this->assertSame(0.0, $this->grading->grade($q, [])->pointsAwarded);
    }

    public function test_fill_blank_respects_the_case_insensitivity_flag(): void
    {
        $insensitive = $this->question(QuestionType::FillBlank, [
            'accepted' => ['Paris', 'City of Light'],
            'case_insensitive' => true,
        ], 1);

        $this->assertTrue($this->grading->grade($insensitive, 'paris')->isCorrect);
        $this->assertTrue($this->grading->grade($insensitive, '  PARIS ')->isCorrect, 'trimmed + folded');
        $this->assertTrue($this->grading->grade($insensitive, 'city of light')->isCorrect);
        $this->assertFalse($this->grading->grade($insensitive, 'london')->isCorrect);

        $sensitive = $this->question(QuestionType::FillBlank, [
            'accepted' => ['Paris'],
            'case_insensitive' => false,
        ], 1);

        $this->assertTrue($this->grading->grade($sensitive, 'Paris')->isCorrect);
        $this->assertFalse($this->grading->grade($sensitive, 'paris')->isCorrect, 'case matters when flag off');
    }

    /**
     * Matching is proportional: points × correctPairs / totalPairs, rounded to 2 dp.
     */
    public function test_matching_is_proportional(): void
    {
        $q = $this->question(QuestionType::Matching, [
            'pairs' => [
                ['id' => 'p1', 'left' => 'Nigeria', 'right' => 'Abuja'],
                ['id' => 'p2', 'left' => 'Ghana', 'right' => 'Accra'],
                ['id' => 'p3', 'left' => 'Kenya', 'right' => 'Nairobi'],
                ['id' => 'p4', 'left' => 'Egypt', 'right' => 'Cairo'],
            ],
        ], 4);

        // No layout supplied → tokens are the pair ids themselves.
        $allRight = ['p1' => 'p1', 'p2' => 'p2', 'p3' => 'p3', 'p4' => 'p4'];
        $this->assertSame(4.0, $this->grading->grade($q, $allRight)->pointsAwarded);
        $this->assertTrue($this->grading->grade($q, $allRight)->isCorrect);

        $threeRight = ['p1' => 'p1', 'p2' => 'p2', 'p3' => 'p3', 'p4' => 'p1'];
        $this->assertSame(3.0, $this->grading->grade($q, $threeRight)->pointsAwarded);
        $this->assertFalse($this->grading->grade($q, $threeRight)->isCorrect);

        // 1 of 4 right on a 5-point question → 1.25.
        $five = $this->question(QuestionType::Matching, $q->payload, 5);
        $oneRight = ['p1' => 'p1', 'p2' => 'p1', 'p3' => 'p1', 'p4' => 'p1'];
        $this->assertSame(1.25, $this->grading->grade($five, $oneRight)->pointsAwarded);
    }

    /**
     * Matching resolves opaque right-tokens via the frozen layout row, so the correct
     * mapping isn't the literal pair id.
     */
    public function test_matching_resolves_layout_tokens(): void
    {
        $q = $this->question(QuestionType::Matching, [
            'pairs' => [
                ['id' => 'p1', 'left' => 'A', 'right' => 'Alpha'],
                ['id' => 'p2', 'left' => 'B', 'right' => 'Beta'],
            ],
        ], 2);

        $layoutRow = [
            'right_tokens' => [
                ['token' => 'r_zzz', 'pair_id' => 'p1'],
                ['token' => 'r_yyy', 'pair_id' => 'p2'],
            ],
        ];

        $correct = ['p1' => 'r_zzz', 'p2' => 'r_yyy'];
        $this->assertSame(2.0, $this->grading->grade($q, $correct, $layoutRow)->pointsAwarded);

        $swapped = ['p1' => 'r_yyy', 'p2' => 'r_zzz'];
        $this->assertSame(0.0, $this->grading->grade($q, $swapped, $layoutRow)->pointsAwarded);
    }

    public function test_essay_is_manual(): void
    {
        $q = $this->question(QuestionType::Essay, ['guidance' => 'x'], 10);

        $result = $this->grading->grade($q, 'My essay answer');
        $this->assertTrue($result->manual);
        $this->assertNull($result->pointsAwarded);
        $this->assertNull($result->isCorrect);
        $this->assertSame(10.0, $result->pointsPossible);
    }

    public function test_objective_scenario_is_auto_graded(): void
    {
        $q = $this->question(QuestionType::Scenario, [
            'sub_questions' => [
                ['id' => 's1', 'type' => 'mcq_single', 'points' => 2, 'payload' => [
                    'options' => [
                        ['id' => 'o1', 'text' => 'A', 'is_correct' => true],
                        ['id' => 'o2', 'text' => 'B', 'is_correct' => false],
                    ],
                ]],
                ['id' => 's2', 'type' => 'true_false', 'points' => 1, 'payload' => [
                    'options' => [
                        ['id' => 'true', 'text' => 'True', 'is_correct' => true],
                        ['id' => 'false', 'text' => 'False', 'is_correct' => false],
                    ],
                ]],
            ],
        ], 3);

        $result = $this->grading->grade($q, ['s1' => 'o1', 's2' => 'false']);
        $this->assertFalse($result->manual);
        $this->assertSame(2.0, $result->pointsAwarded, 's1 right (2), s2 wrong (0)');
        $this->assertSame(3.0, $result->pointsPossible);
        $this->assertFalse($result->isCorrect);
    }

    public function test_scenario_with_an_essay_falls_to_manual(): void
    {
        $q = $this->question(QuestionType::Scenario, [
            'sub_questions' => [
                ['id' => 's1', 'type' => 'mcq_single', 'points' => 2, 'payload' => [
                    'options' => [['id' => 'o1', 'text' => 'A', 'is_correct' => true]],
                ]],
                ['id' => 's2', 'type' => 'essay', 'points' => 5, 'payload' => ['guidance' => 'x']],
            ],
        ], 7);

        $result = $this->grading->grade($q, ['s1' => 'o1', 's2' => 'text']);
        $this->assertTrue($result->manual);
        $this->assertSame(7.0, $result->pointsPossible);

        // The objective subtotal is still available as a grading hint.
        [$awarded, $possible] = $this->grading->scenarioObjectiveSubtotal($q, ['s1' => 'o1', 's2' => 'text'], []);
        $this->assertSame(2.0, $awarded);
        $this->assertSame(2.0, $possible);
    }
}
