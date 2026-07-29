<?php

namespace Tests\Feature\Programmes;

use App\Enums\CourseRequirement;
use App\Enums\EnrollmentMode;
use App\Enums\ProgressionMode;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Programme;
use App\Models\ProgrammePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The case the whole many-to-many design exists for: the published NIPR curriculum
 * lists CPR 112 and CPR 115 under BOTH "CPR Part I" and "Professional Variant Part 1",
 * with different credit loads. A courses.part_id column could not express that.
 */
class ProgrammePlacementTest extends TestCase
{
    use RefreshDatabase;

    private function instructorWithCourse(): array
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);

        return [$instructor, $course];
    }

    public function test_a_course_can_sit_in_two_programmes_with_different_credit_loads(): void
    {
        [, $course] = $this->instructorWithCourse();

        $cpr = Programme::factory()->create(['code' => 'CPR', 'per_paper_fee' => 7000]);
        $npv = Programme::factory()->create(['code' => 'NPV', 'per_paper_fee' => 15000]);
        $cprPart = ProgrammePart::factory()->for($cpr)->named('Part I')->create();
        $npvPart = ProgrammePart::factory()->for($npv)->named('Part 1')->create();

        $course->syncProgrammePlacements([
            ['programme_part_id' => $cprPart->id, 'credit_load' => 3, 'requirement' => 'compulsory', 'is_primary' => true],
            ['programme_part_id' => $npvPart->id, 'credit_load' => null, 'requirement' => 'compulsory', 'is_primary' => false],
        ]);

        $course->load('programmeParts');

        $this->assertCount(2, $course->programmeParts);
        $this->assertSame(3, $course->programmeParts->firstWhere('id', $cprPart->id)->pivot->credit_load);
        $this->assertNull($course->programmeParts->firstWhere('id', $npvPart->id)->pivot->credit_load);
    }

    public function test_the_primary_placement_decides_which_programme_fee_the_course_inherits(): void
    {
        // A paper in both CPR (7,000/paper) and the Variant (15,000/paper) must have one
        // unambiguous answer — otherwise Section 12 cannot price it.
        [, $course] = $this->instructorWithCourse();

        $cpr = Programme::factory()->create(['code' => 'CPR', 'per_paper_fee' => 7000]);
        $npv = Programme::factory()->create(['code' => 'NPV', 'per_paper_fee' => 15000]);

        $course->syncProgrammePlacements([
            ['programme_part_id' => ProgrammePart::factory()->for($cpr)->create()->id, 'is_primary' => true],
            ['programme_part_id' => ProgrammePart::factory()->for($npv)->create()->id, 'is_primary' => false],
        ]);

        $this->assertSame('CPR', $course->primaryProgramme()->code);
        $this->assertSame('7000.00', $course->primaryProgramme()->per_paper_fee);
    }

    public function test_only_one_placement_can_be_primary_however_many_claim_it(): void
    {
        [, $course] = $this->instructorWithCourse();
        $parts = ProgrammePart::factory()->count(3)->create();

        $course->syncProgrammePlacements(
            $parts->map(fn ($part) => ['programme_part_id' => $part->id, 'is_primary' => true])->all(),
        );

        $this->assertSame(1, $course->programmeParts()->wherePivot('is_primary', true)->count());
        $this->assertSame($parts[0]->id, $course->primaryPlacement()->id, 'The first claimant wins.');
    }

    public function test_a_course_with_placements_always_has_exactly_one_primary(): void
    {
        // Nothing claims primary — the first row is promoted, so primaryProgramme()
        // never returns null for a placed course.
        [, $course] = $this->instructorWithCourse();
        $parts = ProgrammePart::factory()->count(2)->create();

        $course->syncProgrammePlacements(
            $parts->map(fn ($part) => ['programme_part_id' => $part->id, 'is_primary' => false])->all(),
        );

        $this->assertSame(1, $course->programmeParts()->wherePivot('is_primary', true)->count());
        $this->assertNotNull($course->primaryProgramme());
    }

    public function test_blank_and_duplicate_rows_are_discarded(): void
    {
        [, $course] = $this->instructorWithCourse();
        $part = ProgrammePart::factory()->create();

        $course->syncProgrammePlacements([
            ['programme_part_id' => $part->id, 'credit_load' => 3],
            ['programme_part_id' => $part->id, 'credit_load' => 9],   // duplicate part
            ['programme_part_id' => 0],                                // empty repeater row
            ['programme_part_id' => ''],
        ]);

        $this->assertCount(1, $course->programmeParts()->get());
        $this->assertSame(3, $course->programmeParts()->first()->pivot->credit_load, 'The first row wins.');
    }

    public function test_an_instructor_sets_placements_from_the_course_settings_form(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();
        $part = ProgrammePart::factory()->create();

        $this->actingAs($instructor)
            ->put(route('courses.update', $course), $this->settingsPayload($course, [
                'placements' => [
                    ['programme_part_id' => $part->id, 'credit_load' => 3, 'requirement' => 'required_elective', 'is_primary' => 1],
                ],
            ]))
            ->assertRedirect(route('courses.edit', $course));

        $placement = $course->fresh()->programmeParts()->first();

        $this->assertSame($part->id, $placement->id);
        $this->assertSame(3, $placement->pivot->credit_load);
        $this->assertSame(CourseRequirement::RequiredElective, $placement->pivot->requirement);
    }

    public function test_settings_reject_a_placement_pointing_at_a_part_that_does_not_exist(): void
    {
        [$instructor, $course] = $this->instructorWithCourse();

        $this->actingAs($instructor)
            ->put(route('courses.update', $course), $this->settingsPayload($course, [
                'placements' => [['programme_part_id' => 99999]],
            ]))
            ->assertSessionHasErrors('placements.0.programme_part_id');

        $this->assertCount(0, $course->fresh()->programmeParts);
    }

    public function test_omitting_placements_entirely_leaves_existing_ones_alone(): void
    {
        // The repeater is not rendered when no programmes exist; a settings save from
        // that form must not silently strip a course out of its qualification.
        [$instructor, $course] = $this->instructorWithCourse();
        $part = ProgrammePart::factory()->create();
        $course->programmeParts()->attach($part->id, ['is_primary' => true]);

        $this->actingAs($instructor)
            ->put(route('courses.update', $course), $this->settingsPayload($course))
            ->assertRedirect();

        $this->assertCount(1, $course->fresh()->programmeParts);
    }

    public function test_a_part_reconciles_its_counted_credits_against_the_stated_target(): void
    {
        // CPR Part I in miniature: the stated total excludes pure electives but includes
        // GNS and required electives.
        $part = ProgrammePart::factory()->create(['credit_target' => 10]);

        $this->attachCourse($part, 4, CourseRequirement::Compulsory);
        $this->attachCourse($part, 4, CourseRequirement::RequiredElective);
        $this->attachCourse($part, 2, CourseRequirement::Gns);
        $this->attachCourse($part, 7, CourseRequirement::Elective);   // outside the total

        $part->load('courses');

        $this->assertSame(10, $part->creditsCounted($part->courses));
        $this->assertSame(17, $part->creditsListed($part->courses));
        $this->assertTrue($part->creditsReconcile($part->courses));
    }

    public function test_a_part_with_no_stated_target_never_reports_a_mismatch(): void
    {
        $part = ProgrammePart::factory()->create(['credit_target' => null]);
        $this->attachCourse($part, 3, CourseRequirement::Compulsory);
        $part->load('courses');

        $this->assertNull($part->creditsReconcile($part->courses));
    }

    private function attachCourse(ProgrammePart $part, int $credits, CourseRequirement $requirement): void
    {
        Course::factory()->published()->create()->programmeParts()->attach($part->id, [
            'credit_load' => $credits,
            'requirement' => $requirement->value,
        ]);
    }

    /**
     * The settings form posts every field; these tests only care about placements, so
     * everything else is echoed back unchanged.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settingsPayload(Course $course, array $overrides = []): array
    {
        return array_merge([
            'title' => $course->title,
            'code' => $course->code,
            'department_id' => $course->department_id,
            'level' => $course->level->value,
            'visibility' => $course->visibility->value,
            // The factory leaves the mode columns at their database defaults rather than
            // setting them, so fall back instead of dereferencing a null enum.
            'enrollment_mode' => ($course->enrollment_mode ?? EnrollmentMode::Open)->value,
            'progression_mode' => ($course->progression_mode ?? ProgressionMode::Free)->value,
        ], $overrides);
    }
}
