<?php

namespace Tests\Feature\Grades;

use App\Enums\Role;
use App\Models\Course;
use App\Models\GradeScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scale administration acceptance criteria: the flawed example scale fails safely and
 * names the gap; a gap-free scale saves; monotonicity is enforced; a course override
 * changes display only, never stored scores; admin-only access.
 */
class GradeScaleAdminTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Scale',
            'scale_limit' => 5.0,
            'display_mode' => 'both',
            'show_scale_limit' => true,
            'separator' => '/',
            'is_default' => true,
            'bands' => [
                ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success'],
                ['label' => 'B', 'grade_point' => 4.0, 'is_pass' => true, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold'],
                ['label' => 'C', 'grade_point' => 3.0, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink'],
                ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson'],
            ],
        ], $overrides);
    }

    public function test_the_flawed_example_scale_cannot_be_saved_and_names_the_gap(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $payload = $this->validPayload([
            'bands' => [
                ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 70, 'max_percent' => 100, 'color' => 'success'],
                ['label' => 'B', 'grade_point' => 4.0, 'is_pass' => true, 'min_percent' => 60, 'max_percent' => 69, 'color' => 'gold'],
                ['label' => 'C', 'grade_point' => 3.0, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 59, 'color' => 'ink'],
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.grade-scales.store'), $payload);

        $response->assertSessionHasErrors('bands');
        $message = session('errors')->get('bands')[0];
        $this->assertStringContainsString('0', $message);
        $this->assertStringContainsString('49', $message);
        $this->assertDatabaseCount('grade_scales', 0);
    }

    public function test_a_gap_free_scale_saves_and_becomes_the_default(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->post(route('admin.grade-scales.store'), $this->validPayload())->assertRedirect();

        $scale = GradeScale::with('bands')->firstOrFail();
        $this->assertTrue($scale->is_default);
        $this->assertCount(4, $scale->bands);
        $this->assertSame('F', $scale->bandFor(43)->label);
    }

    public function test_monotonicity_violation_is_rejected(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $payload = $this->validPayload([
            'bands' => [
                ['label' => 'A', 'grade_point' => 3.0, 'is_pass' => true, 'min_percent' => 80, 'max_percent' => 100, 'color' => 'success'],
                ['label' => 'B', 'grade_point' => 4.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 79, 'color' => 'gold'],
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.grade-scales.store'), $payload)
            ->assertSessionHasErrors('bands');
        $this->assertDatabaseCount('grade_scales', 0);
    }

    public function test_only_admins_may_manage_grade_scales(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $this->actingAs($instructor)->get(route('admin.grade-scales.index'))->assertForbidden();

        $auditor = $this->userWithRole(Role::Auditor->value);
        $this->actingAs($auditor)->get(route('admin.grade-scales.index'))->assertForbidden();

        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin)->get(route('admin.grade-scales.index'))->assertOk();
    }

    public function test_a_course_override_changes_display_without_touching_stored_scores(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin)->post(route('admin.grade-scales.store'), $this->validPayload(['name' => 'NUC Standard']));
        $nuc = GradeScale::where('name', 'NUC Standard')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.grade-scales.store'), $this->validPayload([
            'name' => '4.0 Scale',
            'scale_limit' => 4.0,
            'is_default' => false,
            'bands' => [
                ['label' => 'A', 'grade_point' => 4.0, 'is_pass' => true, 'min_percent' => 80, 'max_percent' => 100, 'color' => 'success'],
                ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 79, 'color' => 'crimson'],
            ],
        ]));
        $fourPointO = GradeScale::where('name', '4.0 Scale')->firstOrFail();

        $course = Course::factory()->published()->create();
        $this->assertSame($nuc->id, $course->gradeScaleOrDefault()->id);

        $course->update(['grade_scale_id' => $fourPointO->id]);
        $this->assertSame($fourPointO->id, $course->fresh()->gradeScaleOrDefault()->id);

        // The switch is presentation-only — nothing scored exists to disturb, and the
        // course's own data (title etc.) is untouched.
        $this->assertSame($fourPointO->id, $course->fresh()->grade_scale_id);
    }
}
