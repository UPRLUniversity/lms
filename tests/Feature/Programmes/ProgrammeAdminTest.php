<?php

namespace Tests\Feature\Programmes;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Programme;
use App\Models\ProgrammePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgrammeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_programme_with_its_fee_schedule(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)
            ->post(route('admin.programmes.store'), [
                'name' => 'Professional Certificate in Public Relations',
                'code' => 'cpr',                     // lower-case on the way in
                'tagline' => 'The entry qualification.',
                'registration_fee' => 20000,
                'administration_fee' => 25000,
                'per_paper_fee' => 7000,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.programmes.index'));

        $programme = Programme::firstWhere('code', 'CPR');

        $this->assertNotNull($programme, 'The code should be normalised to upper case.');
        // Slug comes from the code, so the public filter is /courses?programme=cpr.
        $this->assertSame('cpr', $programme->slug);
        $this->assertSame('7000.00', $programme->per_paper_fee);
        $this->assertSame(45000.0, $programme->entryFee());
    }

    public function test_programme_codes_are_unique_case_insensitively(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        Programme::factory()->create(['code' => 'CPR']);

        $this->actingAs($admin)
            ->post(route('admin.programmes.store'), [
                'name' => 'Another Certificate',
                'code' => 'cpr',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Programme::where('code', 'CPR')->count());
    }

    public function test_an_admin_can_add_a_part_to_a_programme(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $programme = Programme::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.programme-parts.store'), [
                'programme_id' => $programme->id,
                'name' => 'Part I',
                'credit_target' => 24,
            ])
            ->assertRedirect(route('admin.programmes.index'));

        $part = $programme->parts()->firstWhere('name', 'Part I');

        $this->assertNotNull($part);
        $this->assertSame('part-i', $part->slug);
        $this->assertSame(24, $part->credit_target);
    }

    public function test_part_slugs_collide_only_within_a_programme(): void
    {
        // Every programme has a "Part I"; the slug is scoped, not global, so neither
        // should be suffixed.
        $admin = $this->userWithRole(Role::Admin->value);
        $cpr = Programme::factory()->create(['code' => 'CPR']);
        $dpr = Programme::factory()->create(['code' => 'DPR']);

        foreach ([$cpr, $dpr] as $programme) {
            $this->actingAs($admin)->post(route('admin.programme-parts.store'), [
                'programme_id' => $programme->id,
                'name' => 'Part I',
            ]);
        }

        $this->assertSame('part-i', $cpr->parts()->first()->slug);
        $this->assertSame('part-i', $dpr->parts()->first()->slug);
    }

    public function test_a_programme_with_courses_placed_in_it_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $programme = Programme::factory()->create();
        $part = ProgrammePart::factory()->for($programme)->create();
        $course = Course::factory()->published()->create();
        $course->programmeParts()->attach($part->id);

        $this->actingAs($admin)
            ->delete(route('admin.programmes.destroy', $programme))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('programmes', ['id' => $programme->id]);
    }

    public function test_an_empty_programme_can_be_deleted_and_takes_its_parts_with_it(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $programme = Programme::factory()->create();
        $part = ProgrammePart::factory()->for($programme)->create();

        $this->actingAs($admin)
            ->delete(route('admin.programmes.destroy', $programme))
            ->assertRedirect(route('admin.programmes.index'));

        $this->assertDatabaseMissing('programmes', ['id' => $programme->id]);
        $this->assertDatabaseMissing('programme_parts', ['id' => $part->id]);
    }

    public function test_a_part_holding_courses_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $part = ProgrammePart::factory()->create();
        Course::factory()->published()->create()->programmeParts()->attach($part->id);

        $this->actingAs($admin)
            ->delete(route('admin.programme-parts.destroy', $part))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('programme_parts', ['id' => $part->id]);
    }

    public function test_an_instructor_may_read_the_structure_but_not_change_it(): void
    {
        // Instructors hold programmes.view so the course builder can offer the parts,
        // but the structure itself is an admin concern.
        $instructor = $this->userWithRole(Role::Instructor->value);
        $programme = Programme::factory()->create();

        $this->actingAs($instructor)->get(route('admin.programmes.index'))->assertOk();
        $this->actingAs($instructor)->get(route('admin.programmes.create'))->assertForbidden();
        $this->actingAs($instructor)
            ->post(route('admin.programmes.store'), ['name' => 'Sneaky', 'code' => 'SNK'])
            ->assertForbidden();
        $this->actingAs($instructor)
            ->delete(route('admin.programmes.destroy', $programme))
            ->assertForbidden();

        $this->assertDatabaseMissing('programmes', ['code' => 'SNK']);
    }

    public function test_a_student_cannot_reach_the_programme_admin_at_all(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($student)->get(route('admin.programmes.index'))->assertForbidden();
    }

    public function test_an_auditor_sees_the_structure_read_only(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        Programme::factory()->create(['name' => 'Professional Diploma']);

        $this->actingAs($auditor)
            ->get(route('admin.programmes.index'))
            ->assertOk()
            ->assertSee('Professional Diploma')
            ->assertDontSee(route('admin.programmes.create'));
    }
}
