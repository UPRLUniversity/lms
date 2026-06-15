<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Courses\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_sees_their_own_courses_roster_but_not_others(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $mine = Course::factory()->published()->withInstructor($instructor)->create();
        $theirs = Course::factory()->published()->create();

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $mine->id]);

        $this->actingAs($instructor)->get(route('courses.roster', $mine))
            ->assertOk()
            ->assertSee($student->name);

        $this->actingAs($instructor)->get(route('courses.roster', $theirs))->assertForbidden();
    }

    public function test_auditor_can_view_a_roster_but_cannot_withdraw(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        $course = Course::factory()->published()->create();
        $student = $this->userWithRole(Role::Student->value);
        $enrollment = Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($auditor)->get(route('courses.roster', $course))->assertOk();
        $this->actingAs($auditor)
            ->delete(route('courses.roster.withdraw', [$course, $enrollment]))
            ->assertForbidden();
    }

    public function test_admin_can_enrol_a_student_directly_from_the_roster(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->create();
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($admin)
            ->post(route('enrollment.admin.store'), ['user_id' => $student->id, 'course_id' => $course->id])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active->value,
            'source' => 'admin',
        ]);
    }

    public function test_student_cannot_use_the_admin_enrol_endpoint(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        $this->actingAs($student)
            ->post(route('enrollment.admin.store'), ['user_id' => $student->id, 'course_id' => $course->id])
            ->assertForbidden();
    }

    public function test_withdrawing_from_the_roster_auto_promotes_the_waitlist(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->withCapacity(1)->withInstructor($instructor)->create();

        $service = app(EnrollmentService::class);
        $active = $service->selfEnroll($this->userWithRole(Role::Student->value), $course);
        $waiting = $service->selfEnroll($this->userWithRole(Role::Student->value), $course);

        $this->assertSame(EnrollmentStatus::Waitlisted, $waiting->status);

        $this->actingAs($instructor)
            ->delete(route('courses.roster.withdraw', [$course, $active]))
            ->assertRedirect();

        $this->assertSame(EnrollmentStatus::Withdrawn, $active->refresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $waiting->refresh()->status);
    }

    public function test_roster_can_be_exported_as_csv(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->create();
        Enrollment::factory()->active()->create([
            'user_id' => $this->userWithRole(Role::Student->value)->id,
            'course_id' => $course->id,
        ]);

        $this->actingAs($admin)
            ->get(route('courses.roster.export', $course))
            ->assertOk()
            ->assertDownload();
    }
}
