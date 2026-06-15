<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOn(Course $course): Enrollment
    {
        return Enrollment::factory()->pending()->create([
            'user_id' => $this->userWithRole(Role::Student->value)->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_admin_can_approve_a_pending_request(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->approvalMode()->create();
        $enrollment = $this->pendingOn($course);

        $this->actingAs($admin)->post(route('enrollments.approve', $enrollment))->assertRedirect();

        $this->assertSame(EnrollmentStatus::Active, $enrollment->refresh()->status);
        $this->assertSame($admin->id, $enrollment->approved_by);
    }

    public function test_admin_can_reject_with_a_note(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->approvalMode()->create();
        $enrollment = $this->pendingOn($course);

        $this->actingAs($admin)
            ->post(route('enrollments.reject', $enrollment), ['note' => 'Prerequisite not met.'])
            ->assertRedirect();

        $this->assertSame(EnrollmentStatus::Rejected, $enrollment->refresh()->status);
        $this->assertSame('Prerequisite not met.', $enrollment->decision_note);
    }

    public function test_lead_instructor_can_approve_but_co_instructor_cannot(): void
    {
        $lead = $this->userWithRole(Role::Instructor->value);
        $co = $this->userWithRole(Role::Instructor->value);

        $course = Course::factory()->published()->approvalMode()
            ->withInstructor($lead, lead: true)
            ->withInstructor($co, lead: false)
            ->create();

        $enrollment = $this->pendingOn($course);

        // Co-instructor (not lead) is forbidden.
        $this->actingAs($co)->post(route('enrollments.approve', $enrollment))->assertForbidden();
        $this->assertSame(EnrollmentStatus::Pending, $enrollment->refresh()->status);

        // Lead instructor can.
        $this->actingAs($lead)->post(route('enrollments.approve', $enrollment))->assertRedirect();
        $this->assertSame(EnrollmentStatus::Active, $enrollment->refresh()->status);
    }

    public function test_auditor_cannot_approve(): void
    {
        $auditor = $this->userWithRole(Role::Auditor->value);
        $course = Course::factory()->published()->approvalMode()->create();
        $enrollment = $this->pendingOn($course);

        $this->actingAs($auditor)->post(route('enrollments.approve', $enrollment))->assertForbidden();
    }

    public function test_bulk_approve_processes_several_requests(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $course = Course::factory()->published()->approvalMode()->create();
        $a = $this->pendingOn($course);
        $b = $this->pendingOn($course);

        $this->actingAs($admin)
            ->post(route('enrollments.bulk-approve'), ['ids' => [$a->id, $b->id]])
            ->assertRedirect();

        $this->assertSame(EnrollmentStatus::Active, $a->refresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $b->refresh()->status);
    }

    public function test_approval_queue_lists_only_courses_the_instructor_leads(): void
    {
        $lead = $this->userWithRole(Role::Instructor->value);
        $mine = Course::factory()->published()->approvalMode()->withInstructor($lead, lead: true)->create();
        $theirs = Course::factory()->published()->approvalMode()->create();

        $mineStudent = $this->userWithRole(Role::Student->value);
        $theirsStudent = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->pending()->create(['user_id' => $mineStudent->id, 'course_id' => $mine->id]);
        Enrollment::factory()->pending()->create(['user_id' => $theirsStudent->id, 'course_id' => $theirs->id]);

        $this->actingAs($lead)->get(route('enrollments.approvals'))
            ->assertOk()
            ->assertSee($mineStudent->name)
            ->assertDontSee($theirsStudent->name);
    }
}
