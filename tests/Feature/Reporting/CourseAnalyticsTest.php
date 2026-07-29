<?php

namespace Tests\Feature\Reporting;

use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_view_their_course_analytics(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        Enrollment::factory()->count(3)->active()->create(['course_id' => $course->id]);

        $this->actingAs($instructor)->get(route('courses.analytics', $course))
            ->assertOk()
            ->assertSee('Course analytics')
            ->assertSee('Progress distribution')
            ->assertSee('Grade distribution')
            ->assertSee('Knowledge gain')
            ->assertSee('Hardest questions');
    }

    public function test_auditor_can_view_any_course_analytics(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        $course = Course::factory()->published()->create();

        $this->actingAs($auditor)->get(route('courses.analytics', $course))->assertOk();
    }

    public function test_a_student_cannot_view_course_analytics(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        $this->actingAs($student)->get(route('courses.analytics', $course))->assertForbidden();
    }

    public function test_an_instructor_cannot_view_another_instructors_course_analytics(): void
    {
        $mine = $this->userWithRole(Role::Instructor->value);
        $theirs = User::factory()->create();
        $course = Course::factory()->published()->create(['created_by' => $theirs->id]);

        $this->actingAs($mine)->get(route('courses.analytics', $course))->assertForbidden();
    }
}
