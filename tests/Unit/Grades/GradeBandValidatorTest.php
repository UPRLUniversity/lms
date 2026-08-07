<?php

namespace Tests\Unit\Grades;

use App\Support\Grades\GradeBandValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The invariant matrix: contiguous 0–100 coverage, strictly-decreasing grade points from
 * the top band down, nothing above the scale limit, at least two bands, unique labels.
 * No database touched — GradeBandValidator operates purely on arrays — but it throws a
 * real Illuminate ValidationException, which needs the framework container, so this
 * extends the app TestCase rather than bare PHPUnit.
 */
class GradeBandValidatorTest extends TestCase
{
    /**
     * `is_pass` defaults to the same derivation the Section 18 migration used to backfill
     * live bands (`grade_point > 0`), so the coverage/monotonicity cases below keep testing
     * what they were written to test rather than tripping the pass invariants first.
     */
    private function band(string $label, float $point, int $min, int $max, ?bool $isPass = null): array
    {
        return [
            'label' => $label,
            'grade_point' => $point,
            'is_pass' => $isPass ?? $point > 0,
            'min_percent' => $min,
            'max_percent' => $max,
        ];
    }

    public function test_the_flawed_example_scale_fails_safely_and_names_the_gap(): void
    {
        // The team's flawed example: bottom band starts at 50, leaving 0–49 uncovered.
        $bands = [
            $this->band('A', 5.0, 70, 100),
            $this->band('B', 4.0, 60, 69),
            $this->band('C', 3.0, 50, 59),
        ];

        try {
            GradeBandValidator::validate($bands, 5.0);
            $this->fail('Expected a ValidationException for the uncovered 0–49 range.');
        } catch (ValidationException $e) {
            $message = $e->validator->errors()->first('bands');
            $this->assertStringContainsString('0', $message);
            $this->assertStringContainsString('49', $message);
        }
    }

    public function test_a_gap_free_scale_passes(): void
    {
        $bands = [
            $this->band('A', 5.0, 70, 100),
            $this->band('B', 4.0, 60, 69),
            $this->band('C', 3.0, 50, 59),
            $this->band('F', 0.0, 0, 49),
        ];

        GradeBandValidator::validate($bands, 5.0);
        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_an_overlap_is_rejected(): void
    {
        $bands = [
            $this->band('A', 5.0, 60, 100), // overlaps B on 60–69
            $this->band('B', 4.0, 60, 79),
            $this->band('F', 0.0, 0, 59),
        ];

        $this->expectException(ValidationException::class);
        GradeBandValidator::validate($bands, 5.0);
    }

    public function test_monotonicity_is_enforced_top_to_bottom(): void
    {
        // B (60–79) carries a HIGHER point than A (80–100) — invalid.
        $bands = [
            $this->band('A', 3.0, 80, 100),
            $this->band('B', 4.0, 60, 79),
            $this->band('F', 0.0, 0, 59),
        ];

        try {
            GradeBandValidator::validate($bands, 5.0);
            $this->fail('Expected a monotonicity violation.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->validator->errors()->get('bands'));
        }
    }

    public function test_a_grade_point_above_the_scale_limit_is_rejected(): void
    {
        $bands = [
            $this->band('A', 6.0, 50, 100), // exceeds the 5.0 limit
            $this->band('F', 0.0, 0, 49),
        ];

        $this->expectException(ValidationException::class);
        GradeBandValidator::validate($bands, 5.0);
    }

    public function test_at_least_two_bands_are_required(): void
    {
        $bands = [$this->band('A', 5.0, 0, 100)];

        $this->expectException(ValidationException::class);
        GradeBandValidator::validate($bands, 5.0);
    }

    public function test_labels_must_be_unique(): void
    {
        $bands = [
            $this->band('A', 5.0, 50, 100),
            $this->band('a', 0.0, 0, 49), // same label, different case
        ];

        $this->expectException(ValidationException::class);
        GradeBandValidator::validate($bands, 5.0);
    }

    public function test_a_scale_that_cannot_express_both_outcomes_is_rejected(): void
    {
        $allPass = [
            $this->band('A', 5.0, 50, 100, true),
            $this->band('B', 4.0, 0, 49, true),
        ];
        $allFail = [
            $this->band('A', 5.0, 50, 100, false),
            $this->band('F', 0.0, 0, 49, false),
        ];

        foreach ([$allPass, $allFail] as $bands) {
            $this->expectingValidationFailure(fn () => GradeBandValidator::validate($bands, 5.0));
        }
    }

    public function test_a_failing_band_above_a_passing_one_is_rejected(): void
    {
        // C passes but B, which covers a higher range, does not — so no single percentage
        // is "the pass mark", and every screen quoting one would be lying.
        $bands = [
            $this->band('A', 5.0, 70, 100, true),
            $this->band('B', 4.0, 60, 69, false),
            $this->band('C', 3.0, 50, 59, true),
            $this->band('F', 0.0, 0, 49, false),
        ];

        $this->expectingValidationFailure(fn () => GradeBandValidator::validate($bands, 5.0));
    }

    public function test_a_failing_band_may_carry_a_grade_point(): void
    {
        // 0.5 for a near-miss that is still a fail — the case a computed
        // `grade_point > 0` rule would get wrong, and the reason is_pass is a column.
        $bands = [
            $this->band('A', 5.0, 50, 100, true),
            $this->band('D', 0.5, 40, 49, false),
            $this->band('F', 0.0, 0, 39, false),
        ];

        GradeBandValidator::validate($bands, 5.0);
        $this->addToAssertionCount(1); // no exception thrown
    }

    private function expectingValidationFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->validator->errors()->get('bands'));
        }
    }

    public function test_a_band_extending_past_100_is_rejected(): void
    {
        $bands = [
            $this->band('A', 5.0, 50, 120),
            $this->band('F', 0.0, 0, 49),
        ];

        $this->expectException(ValidationException::class);
        GradeBandValidator::validate($bands, 5.0);
    }
}
