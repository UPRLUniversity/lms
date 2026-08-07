<?php

namespace Tests\Feature\Grades;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Access control for the gradebook: a student sees only their own; the instructor
 * matrix is scoped to courses taught; admins reach any course; the auditor is
 * read-only (it can view the matrix but never recompute).
 */
class GradebookAccessTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithScale(): Course
    {
        $scale = GradeScale::factory()->default()->create();
        $scale->bands()->createMany([
            ['label' => 'A', 'grade_point' => 5.0, 'is_pass' => true, 'min_percent' => 50, 'max_percent' => 100, 'color' => 'success', 'position' => 0],
            ['label' => 'F', 'grade_point' => 0.0, 'is_pass' => false, 'min_percent' => 0, 'max_percent' => 49, 'color' => 'crimson', 'position' => 1],
        ]);

        return Course::factory()->published()->create();
    }

    public function test_a_student_sees_their_own_grades_but_not_another_students(): void
    {
        $course = $this->courseWithScale();
        Assessment::factory()->published()->create(['course_id' => $course->id, 'is_required' => true]);

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($student)->get(route('learn.grades', $course))->assertOk();

        $outsider = $this->userWithRole(Role::Student->value);
        $this->actingAs($outsider)->get(route('learn.grades', $course))->assertForbidden();
    }

    public function test_the_instructor_matrix_is_scoped_to_courses_taught(): void
    {
        $course = $this->courseWithScale();
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course->instructors()->attach($instructor->id, ['is_lead' => true]);

        $this->actingAs($instructor)->get(route('courses.gradebook', $course))->assertOk();

        $outsider = $this->userWithRole(Role::Instructor->value);
        $this->actingAs($outsider)->get(route('courses.gradebook', $course))->assertForbidden();

        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin)->get(route('courses.gradebook', $course))->assertOk();
    }

    public function test_the_auditor_views_the_matrix_read_only_and_cannot_recompute(): void
    {
        $course = $this->courseWithScale();
        $auditor = $this->userWithRole(Role::Auditor->value);

        $this->actingAs($auditor)->get(route('courses.gradebook', $course))->assertOk();

        $student = $this->userWithRole(Role::Student->value);
        $this->actingAs($auditor)->post(route('courses.gradebook.recompute', [$course, $student]))->assertForbidden();

        // An instructor (even one who teaches the course) may not recompute either —
        // it's an explicit ADMIN action.
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course->instructors()->attach($instructor->id, ['is_lead' => true]);
        $this->actingAs($instructor)->post(route('courses.gradebook.recompute', [$course, $student]))->assertForbidden();

        $admin = $this->userWithRole(Role::Admin->value);
        $this->actingAs($admin)->post(route('courses.gradebook.recompute', [$course, $student]))->assertRedirect();
    }
}
