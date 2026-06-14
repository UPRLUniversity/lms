<?php

namespace Tests\Feature\Courses;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_faculty_and_department(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->post(route('admin.faculties.store'), [
            'name' => 'Faculty of Communication & Media Studies',
        ])->assertRedirect(route('admin.faculties.index'));

        $faculty = Faculty::firstOrFail();
        $this->assertNotEmpty($faculty->slug);

        $this->actingAs($admin)->post(route('admin.departments.store'), [
            'faculty_id' => $faculty->id,
            'name' => 'Department of Public Relations',
        ])->assertRedirect(route('admin.faculties.index'));

        $this->assertDatabaseHas('departments', ['name' => 'Department of Public Relations', 'faculty_id' => $faculty->id]);
    }

    public function test_a_faculty_with_courses_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $department = Department::factory()->create();
        Course::factory()->create(['department_id' => $department->id]);

        $this->actingAs($admin)->delete(route('admin.faculties.destroy', $department->faculty));

        $this->assertDatabaseHas('faculties', ['id' => $department->faculty->id]);
    }

    public function test_instructor_cannot_manage_the_academic_structure(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);

        $this->actingAs($instructor)->get(route('admin.faculties.index'))->assertForbidden();
        $this->actingAs($instructor)->post(route('admin.faculties.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_auditor_can_view_but_not_manage_the_structure(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        Faculty::factory()->create(['name' => 'Faculty of Leadership']);

        $this->actingAs($auditor)->get(route('admin.faculties.index'))
            ->assertOk()->assertSee('Faculty of Leadership');

        $this->actingAs($auditor)->post(route('admin.faculties.store'), ['name' => 'New'])->assertForbidden();
    }

    public function test_student_cannot_reach_the_structure(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($student)->get(route('admin.faculties.index'))->assertForbidden();
    }
}
