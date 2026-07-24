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
    private function band(string $label, float $point, int $min, int $max): array
    {
        return ['label' => $label, 'grade_point' => $point, 'min_percent' => $min, 'max_percent' => $max];
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
