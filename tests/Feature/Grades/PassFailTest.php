<?php

namespace Tests\Feature\Grades;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\GradeScale;
use App\Services\Courses\LearningService;
use Database\Seeders\GradeScaleSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Section 18 — pass/fail on grade bands. Four things have to hold:
 *
 *   1. The backfill reproduces the existing intent of both seeded scales exactly.
 *   2. A recorded grade keeps the verdict it was AWARDED under, even after the live
 *      scale is re-cut — the reason the verdict is read from `scale_snapshot`.
 *   3. A scale that cannot express both outcomes is refused at the door.
 *   4. Pass and grade point are genuinely independent, which is the whole reason
 *      `is_pass` is a column rather than `grade_point > 0` computed at read time.
 */
class PassFailTest extends TestCase
{
    use RefreshDatabase;

    private function scale(int $passFrom = 40): GradeScale
    {
        $scale = GradeScale::factory()->default()->create(['scale_limit' => 5.0]);
        $scale->bands()->createMany([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => $passFrom, 'max_percent' => 100, 'color' => 'success', 'position' => 0],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => $passFrom - 1, 'color' => 'crimson', 'position' => 1],
        ]);

        return $scale->load('bands');
    }

    /**
     * Drives a student all the way to Completed on a single required assessment, which is
     * the only path that writes a CourseGradeRecord.
     */
    private function completeWith(int $percent, ?Course $course = null): CourseGradeRecord
    {
        $course ??= Course::factory()->published()->create();
        $student = $this->userWithRole(Role::Student->value);

        $assessment = Assessment::factory()->published()->create([
            'course_id' => $course->id, 'is_required' => true, 'passing_score' => 10,
        ]);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create([
            'user_id' => $student->id, 'course_id' => $course->id,
        ]);
        Attempt::factory()->create([
            'assessment_id' => $assessment->id, 'user_id' => $student->id, 'status' => 'graded',
            'score' => $percent, 'max_score' => 100, 'percentage' => $percent, 'passed' => true,
        ]);

        app(LearningService::class)->recalculate($student, $course);

        return CourseGradeRecord::query()
            ->where('user_id', $student->id)->where('course_id', $course->id)
            ->current()->firstOrFail();
    }

    private function payload(array $bands, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Scale',
            'scale_limit' => 5.0,
            'display_mode' => 'both',
            'show_scale_limit' => true,
            'separator' => '/',
            'is_default' => true,
            'bands' => $bands,
        ], $overrides);
    }

    // ---------------------------------------------------------------- the backfill

    public function test_the_backfill_reproduces_existing_intent_for_both_seeded_scales(): void
    {
        $this->seed(GradeScaleSeeder::class);

        // Wind the column back to its pre-Section-18 state and re-run the migration, so
        // the backfill runs against real rows exactly as it did against the live database.
        Schema::table('grade_bands', fn (Blueprint $table) => $table->dropColumn('is_pass'));
        (require database_path('migrations/2026_08_07_100000_add_is_pass_to_grade_bands.php'))->up();

        $nuc = GradeScale::query()->where('name', 'NUC Standard (5.0)')->with('bands')->firstOrFail();
        $four = GradeScale::query()->where('name', '4.0 Scale')->with('bands')->firstOrFail();

        // Every band with a grade point passes; the zero-point fail band, and only it, fails.
        foreach ([$nuc, $four] as $scale) {
            foreach ($scale->bands as $band) {
                $this->assertSame(
                    (float) $band->grade_point > 0,
                    $band->is_pass,
                    "{$scale->name} band {$band->label} did not backfill to its grade point's intent."
                );
            }
        }

        $this->assertSame('F', $nuc->bands->firstWhere('is_pass', false)->label);
        $this->assertSame('F', $four->bands->firstWhere('is_pass', false)->label);

        // E is the lowest NUC pass (40–44); D the lowest on the 4-point scale (50–59).
        $this->assertSame(40, $nuc->passMark());
        $this->assertSame(50, $four->passMark());
    }

    // ---------------------------------------------------- the frozen verdict (§0.4)

    public function test_a_recorded_verdict_survives_the_live_scale_being_re_cut(): void
    {
        $scale = $this->scale(passFrom: 40);
        $record = $this->completeWith(55);

        $this->assertTrue($record->isPass());

        // The registrar raises the bar: 55% is now a fail on the LIVE scale.
        $scale->bands()->delete();
        $scale->bands()->createMany([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success', 'position' => 0],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 69, 'color' => 'crimson', 'position' => 1],
        ]);

        // The record keeps the verdict it was awarded under. Anything else would fail a
        // student retroactively — and would start refusing progression it already allowed.
        $this->assertTrue($record->fresh()->isPass());
    }

    public function test_a_recorded_fail_reads_as_a_fail(): void
    {
        $this->scale(passFrom: 40);
        $record = $this->completeWith(25);

        $this->assertSame('F', $record->grade_label);
        $this->assertFalse($record->isPass());
        $this->assertSame('Fail', $record->outcomeLabel());
    }

    public function test_a_record_stamped_before_is_pass_existed_falls_back_to_the_grade_point(): void
    {
        $passing = CourseGradeRecord::factory()->legacySnapshot()->create([
            'final_percent' => 82, 'grade_label' => 'A', 'grade_point' => 5.0,
        ]);
        $failing = CourseGradeRecord::factory()->failed()->legacySnapshot()->create();

        $this->assertTrue($passing->isPass());
        $this->assertFalse($failing->isPass());
    }

    // ------------------------------------------------- the scale invariants (§0.2)

    public function test_a_scale_with_no_failing_band_is_refused(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->post(route('admin.grade-scales.store'), $this->payload([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'B', 'grade_point' => 4.0, 'is_pass' => true, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'gold'],
        ]))->assertSessionHasErrors('bands');

        $this->assertStringContainsString('fail', session('errors')->get('bands')[0]);
        $this->assertDatabaseCount('grade_scales', 0);
    }

    public function test_a_scale_with_no_passing_band_is_refused(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->post(route('admin.grade-scales.store'), $this->payload([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => false, 'min_percent' => 50, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson'],
        ]))->assertSessionHasErrors('bands');

        $this->assertStringContainsString('pass', session('errors')->get('bands')[0]);
        $this->assertDatabaseCount('grade_scales', 0);
    }

    public function test_a_fail_sitting_above_a_pass_is_refused(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        // C (50–59) passes but B (60–69) above it does not — "pass mark: 50%" would be a lie.
        $this->actingAs($admin)->post(route('admin.grade-scales.store'), $this->payload([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'B', 'grade_point' => 4.0, 'is_pass' => false, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold'],
            ['label' => 'C', 'grade_point' => 3.0, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink'],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson'],
        ]))->assertSessionHasErrors('bands');

        $this->assertDatabaseCount('grade_scales', 0);
    }

    public function test_a_band_that_states_no_pass_choice_is_refused(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->post(route('admin.grade-scales.store'), $this->payload([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'F', 'grade_point' => 0.0, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson'],
        ]))->assertSessionHasErrors('bands.1.is_pass');

        $this->assertDatabaseCount('grade_scales', 0);
    }

    // ------------------------------------------ pass is not the grade point (§6.1)

    public function test_a_failing_band_may_still_carry_a_grade_point(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        // The near-miss case: 0.5 for a D that is still a fail. A computed
        // `grade_point > 0` rule would silently pass this student.
        $this->actingAs($admin)->post(route('admin.grade-scales.store'), $this->payload([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 100, 'color' => 'success'],
            ['label' => 'D', 'grade_point' => 0.5, 'is_pass' => false, 'min_percent' => 40, 'max_percent' => 49, 'color' => 'neutral'],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 39, 'color' => 'crimson'],
        ]))->assertRedirect();

        $scale = GradeScale::query()->with('bands')->firstOrFail();
        $near = $scale->bands->firstWhere('label', 'D');

        $this->assertFalse($near->is_pass);
        $this->assertSame(0.5, (float) $near->grade_point);
        $this->assertSame(50, $scale->passMark());
    }

    // ------------------------------------------------------------- what people see

    public function test_the_student_grade_page_states_the_outcome_and_the_pass_mark(): void
    {
        $this->scale(passFrom: 40);
        $course = Course::factory()->published()->create();
        $record = $this->completeWith(25, $course);

        $this->actingAs($record->user)->get(route('learn.grades', $course))
            ->assertOk()
            ->assertSee('Fail')
            ->assertSee('pass mark 40%');
    }

    public function test_the_certificate_queue_flags_a_student_whose_recorded_grade_is_a_fail(): void
    {
        $this->scale(passFrom: 40);
        $course = Course::factory()->published()->create();
        $record = $this->completeWith(25, $course);

        // Certificates are issued on completion, so clear it to reach the manual queue.
        $record->user->certificates()->delete();

        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->get(route('admin.certificates.index'))
            ->assertOk()
            ->assertSee('Recorded a fail');
    }
}
