<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_learning_shows_status_aware_cards(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $active = Course::factory()->published()->create(['title' => 'Active Course']);
        $pending = Course::factory()->published()->approvalMode()->create(['title' => 'Pending Course']);

        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $active->id]);
        Enrollment::factory()->pending()->create(['user_id' => $student->id, 'course_id' => $pending->id]);

        $this->actingAs($student)->get(route('learning.index'))
            ->assertOk()
            ->assertSee('Active Course')
            ->assertSee('Continue learning')
            ->assertSee('Pending Course')
            ->assertSee('Pending approval');
    }

    public function test_empty_state_when_not_enrolled(): void
    {
        $student = $this->userWithRole(Role::Student->value);

        $this->actingAs($student)->get(route('learning.index'))
            ->assertOk()
            ->assertSee("haven't enrolled yet");
    }

    public function test_student_can_withdraw_from_my_learning(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($student)->delete(route('enrollment.withdraw', $enrollment))->assertRedirect();

        $this->assertSame(EnrollmentStatus::Withdrawn, $enrollment->refresh()->status);
    }

    public function test_a_student_cannot_withdraw_someone_elses_enrolment(): void
    {
        $owner = $this->userWithRole(Role::Student->value);
        $other = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->active()->create(['user_id' => $owner->id, 'course_id' => $course->id]);

        $this->actingAs($other)->delete(route('enrollment.withdraw', $enrollment))->assertForbidden();
        $this->assertSame(EnrollmentStatus::Active, $enrollment->refresh()->status);
    }

    public function test_rejected_and_withdrawn_enrolments_are_hidden(): void
    {
        $student = $this->userWithRole(Role::Student->value);
        $course = Course::factory()->published()->create(['title' => 'Gone Course']);
        Enrollment::factory()->withdrawn()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($student)->get(route('learning.index'))
            ->assertOk()
            ->assertDontSee('Gone Course');
    }
}
