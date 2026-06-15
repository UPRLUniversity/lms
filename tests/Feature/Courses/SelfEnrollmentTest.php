<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Courses\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_course_enrols_student_to_active_via_http(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        $this->actingAs($student)
            ->post(route('enrollment.store', $course))
            ->assertRedirect(route('learning.index'));

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active->value,
            'source' => 'self',
        ]);
    }

    public function test_approval_course_creates_a_pending_request(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->approvalMode()->create();

        $this->actingAs($student)->post(route('enrollment.store', $course))->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Pending->value,
        ]);
    }

    public function test_full_course_joins_the_waitlist(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->withCapacity(1)->create();
        // Fill the single seat with someone else.
        app(EnrollmentService::class)->selfEnroll($this->userWithRole(Role::Student->value), $course);

        $this->actingAs($student)->post(route('enrollment.store', $course))->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'status' => EnrollmentStatus::Waitlisted->value,
        ]);
    }

    public function test_invite_only_course_cannot_be_self_enrolled(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->inviteOnly()->create();

        $this->actingAs($student)
            ->post(route('enrollment.store', $course))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_duplicate_enrolment_is_impossible(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();

        $this->actingAs($student)->post(route('enrollment.store', $course));
        $this->actingAs($student)->post(route('enrollment.store', $course))->assertSessionHas('error');

        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->count());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $course = Course::factory()->published()->create();

        $this->post(route('enrollment.store', $course))->assertRedirect(route('login'));
    }

    public function test_catalogue_page_shows_the_right_cta_per_mode(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $open = Course::factory()->published()->create();
        $this->actingAs($student)->get(route('catalogue.show', $open))->assertSee('Enrol — start learning');

        $approval = Course::factory()->published()->approvalMode()->create();
        $this->actingAs($student)->get(route('catalogue.show', $approval))->assertSee('Request enrolment');

        $invite = Course::factory()->published()->inviteOnly()->create();
        $this->actingAs($student)->get(route('catalogue.show', $invite))->assertSee('Enrolment by invitation');
    }

    public function test_enrolled_student_sees_continue_state_on_course_page(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        app(EnrollmentService::class)->selfEnroll($student, $course);

        $this->actingAs($student)->get(route('catalogue.show', $course))
            ->assertSee("You're enrolled")
            ->assertSee('Continue learning');
    }
}
