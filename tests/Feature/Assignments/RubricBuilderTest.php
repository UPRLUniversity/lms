<?php

namespace Tests\Feature\Assignments;

use App\Enums\Role;
use App\Models\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function grid(): array
    {
        return [
            'name' => 'Essay rubric',
            'criteria' => [
                [
                    'title' => 'Argument',
                    'description' => 'Quality of the thesis and reasoning.',
                    'levels' => [
                        ['label' => 'Excellent', 'description' => 'Compelling.', 'points' => 10],
                        ['label' => 'Good', 'description' => 'Sound.', 'points' => 7],
                        ['label' => 'Weak', 'description' => 'Unclear.', 'points' => 3],
                    ],
                ],
                [
                    'title' => 'Sources',
                    'levels' => [
                        ['label' => 'Rich', 'points' => 5],
                        ['label' => 'Thin', 'points' => 2],
                    ],
                ],
            ],
        ];
    }

    public function test_an_instructor_builds_a_rubric_grid_and_the_math_holds(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);

        $this->actingAs($instructor)->post(route('rubrics.store'), $this->grid())->assertRedirect();

        $rubric = Rubric::with('criteria')->firstOrFail();
        $this->assertSame('Essay rubric', $rubric->name);
        $this->assertSame(['Argument', 'Sources'], $rubric->criteria->pluck('title')->all());
        $this->assertSame(15.0, $rubric->totalPoints());
        $this->assertSame('Good', $rubric->criteria->first()->level(1)['label']);
    }

    public function test_a_rubric_needs_at_least_one_criterion_with_two_levels(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);

        $this->actingAs($instructor)->post(route('rubrics.store'), [
            'name' => 'Empty', 'criteria' => [],
        ])->assertSessionHasErrors('criteria');

        $bad = $this->grid();
        $bad['criteria'][0]['levels'] = [['label' => 'Only one', 'points' => 5]];
        $this->actingAs($instructor)->post(route('rubrics.store'), $bad)
            ->assertSessionHasErrors('criteria.0.levels');
    }

    public function test_only_the_owner_or_an_admin_may_edit_a_rubric(): void
    {
        $owner = $this->userWithRole(Role::Instructor->value);
        $rubric = Rubric::factory()->withCriteria()->create(['created_by' => $owner->id]);

        $other = $this->userWithRole(Role::Instructor->value);
        $this->actingAs($other)->get(route('rubrics.edit', $rubric))->assertForbidden();
        $this->actingAs($other)->put(route('rubrics.update', $rubric), $this->grid())->assertForbidden();

        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin)->get(route('rubrics.edit', $rubric))->assertOk();

        $auditor = $this->userWithRole(Role::Auditor->value);
        $this->actingAs($auditor)->get(route('rubrics.edit', $rubric))->assertOk();
        $this->actingAs($auditor)->put(route('rubrics.update', $rubric), $this->grid())->assertForbidden();

        $student = $this->userWithRole(Role::Student->value);
        $this->actingAs($student)->get(route('rubrics.index'))->assertForbidden();
    }

    public function test_students_never_reach_the_rubric_library(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $this->actingAs($student)->get(route('rubrics.create'))->assertForbidden();
        $this->actingAs($student)->post(route('rubrics.store'), $this->grid())->assertForbidden();
    }
}
