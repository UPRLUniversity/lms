<?php

namespace Tests\Feature\Courses;

use App\Enums\Role;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_list_shows_only_their_own_courses(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $mine = Course::factory()->withInstructor($instructor)->create(['title' => 'My Course', 'created_by' => $instructor->id]);
        $theirs = Course::factory()->create(['title' => 'Someone Elses Course']);

        $this->actingAs($instructor)->get(route('courses.index'))
            ->assertOk()
            ->assertSee('My Course')
            ->assertDontSee('Someone Elses Course');
    }

    public function test_admin_list_shows_every_course(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        Course::factory()->create(['title' => 'Course Alpha']);
        Course::factory()->create(['title' => 'Course Beta']);

        $this->actingAs($admin)->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Course Alpha')
            ->assertSee('Course Beta');
    }

    public function test_students_cannot_reach_the_management_area(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($student)->get(route('courses.index'))->assertForbidden();
        $this->actingAs($student)->get(route('courses.create'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login_from_the_builder(): void
    {
        $course = Course::factory()->create();

        $this->get(route('courses.edit', $course))->assertRedirect(route('login'));
    }

    public function test_auditor_can_view_a_course_builder_read_only(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        $course = Course::factory()->create();

        // Can view…
        $this->actingAs($auditor)->get(route('courses.edit', $course))->assertOk();

        // …but cannot mutate.
        $this->actingAs($auditor)->postJson(route('modules.store', $course), ['title' => 'No'])->assertForbidden();
    }
}
