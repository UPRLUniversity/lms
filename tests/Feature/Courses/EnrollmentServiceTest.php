<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Exceptions\EnrollmentException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Courses\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EnrollmentService
    {
        return app(EnrollmentService::class);
    }

    private function student(): User
    {
        return $this->userWithRole(Role::Student->value);
    }

    public function test_open_course_self_enrolls_straight_to_active(): void
    {
        $course = Course::factory()->published()->create();

        $enrollment = $this->service()->selfEnroll($this->student(), $course);

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_approval_course_self_enrols_to_pending(): void
    {
        $course = Course::factory()->published()->approvalMode()->create();

        $enrollment = $this->service()->selfEnroll($this->student(), $course);

        $this->assertSame(EnrollmentStatus::Pending, $enrollment->status);
    }

    public function test_invite_only_course_rejects_self_enrolment(): void
    {
        $course = Course::factory()->published()->inviteOnly()->create();

        $this->expectException(EnrollmentException::class);
        $this->service()->selfEnroll($this->student(), $course);
    }

    public function test_closed_window_rejects_self_enrolment(): void
    {
        $course = Course::factory()->published()
            ->window(now()->subWeek(), now()->subDay())
            ->create();

        $this->expectException(EnrollmentException::class);
        $this->service()->selfEnroll($this->student(), $course);
    }

    public function test_full_course_waitlists_with_position(): void
    {
        $course = Course::factory()->published()->withCapacity(2)->create();

        $this->service()->selfEnroll($this->student(), $course);
        $this->service()->selfEnroll($this->student(), $course);
        $third = $this->service()->selfEnroll($this->student(), $course);

        $this->assertSame(EnrollmentStatus::Waitlisted, $third->status);
        $this->assertSame(1, $third->waitlistPosition());
    }

    public function test_duplicate_enrolment_is_rejected(): void
    {
        $course = Course::factory()->published()->create();
        $student = $this->student();

        $this->service()->selfEnroll($student, $course);

        $this->expectException(EnrollmentException::class);
        $this->service()->selfEnroll($student, $course);

        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->count());
    }

    public function test_withdrawing_an_active_student_auto_promotes_the_first_waitlisted_and_renumbers(): void
    {
        // Capacity 3, filled, with two on the waitlist.
        $course = Course::factory()->published()->withCapacity(3)->create();

        $active = collect(range(1, 3))->map(fn () => $this->service()->selfEnroll($this->student(), $course));
        $first = $this->service()->selfEnroll($this->student(), $course);   // waitlist #1
        $second = $this->service()->selfEnroll($this->student(), $course);  // waitlist #2

        $this->assertSame(1, $first->waitlistPosition());
        $this->assertSame(2, $second->waitlistPosition());

        // Withdraw one active student → a seat frees.
        $this->service()->withdraw($active->first());

        // The earliest waitlisted is now active; the other renumbers to #1.
        $this->assertSame(EnrollmentStatus::Active, $first->refresh()->status);
        $this->assertSame(EnrollmentStatus::Waitlisted, $second->refresh()->status);
        $this->assertSame(1, $second->waitlistPosition());
    }

    public function test_approval_course_promotes_waitlisted_to_pending_not_active(): void
    {
        $course = Course::factory()->published()->approvalMode()->withCapacity(1)->create();

        $seated = $this->service()->selfEnroll($this->student(), $course);   // pending (holds the seat)
        $waiting = $this->service()->selfEnroll($this->student(), $course);  // waitlisted

        $this->assertSame(EnrollmentStatus::Pending, $seated->status);
        $this->assertSame(EnrollmentStatus::Waitlisted, $waiting->status);

        // Free the seat: reject the pending request.
        $admin = $this->userWithRole(Role::Admin->value);
        $this->service()->reject($seated, $admin);

        $this->assertSame(EnrollmentStatus::Pending, $waiting->refresh()->status);
    }

    public function test_promotion_never_double_promotes_under_repeated_syncs(): void
    {
        // The concurrency-safety guarantee: even if the promotion runs twice (two
        // racing triggers), the recount-after-lock means only the freed seats fill.
        $course = Course::factory()->published()->withCapacity(3)->create();

        $active = collect(range(1, 3))->map(fn () => $this->service()->selfEnroll($this->student(), $course));
        $w1 = $this->service()->selfEnroll($this->student(), $course);
        $w2 = $this->service()->selfEnroll($this->student(), $course);

        // Free exactly one seat WITHOUT going through withdraw() (so no sync fires yet).
        $active->first()->forceFill(['status' => EnrollmentStatus::Withdrawn->value])->save();

        // Two back-to-back syncs (simulating a race) must promote exactly one student.
        $promotedA = $this->service()->syncWaitlist($course);
        $promotedB = $this->service()->syncWaitlist($course);

        $this->assertSame(1, $promotedA);
        $this->assertSame(0, $promotedB);
        $this->assertSame(EnrollmentStatus::Active, $w1->refresh()->status);
        $this->assertSame(EnrollmentStatus::Waitlisted, $w2->refresh()->status);
    }

    public function test_raising_capacity_promotes_from_the_waitlist(): void
    {
        $course = Course::factory()->published()->withCapacity(1)->create();

        $this->service()->selfEnroll($this->student(), $course);             // active
        $waiting = $this->service()->selfEnroll($this->student(), $course);  // waitlisted

        $course->update(['capacity' => 2]);
        $this->service()->capacityChanged($course);

        $this->assertSame(EnrollmentStatus::Active, $waiting->refresh()->status);
    }

    public function test_admin_enroll_may_exceed_capacity(): void
    {
        $course = Course::factory()->published()->withCapacity(1)->create();
        $admin = $this->userWithRole(Role::Admin->value);

        $this->service()->selfEnroll($this->student(), $course);             // fills the one seat
        $forced = $this->service()->adminEnroll($this->student(), $course, $admin);

        $this->assertSame(EnrollmentStatus::Active, $forced->status);
        $this->assertSame(2, $course->enrollments()->where('status', EnrollmentStatus::Active->value)->count());
    }

    public function test_re_enrolment_after_withdrawal_reuses_the_same_row(): void
    {
        $course = Course::factory()->published()->create();
        $student = $this->student();

        $first = $this->service()->selfEnroll($student, $course);
        $this->service()->withdraw($first);

        $second = $this->service()->selfEnroll($student, $course);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(EnrollmentStatus::Active, $second->status);
        $this->assertSame(1, Enrollment::where('user_id', $student->id)->where('course_id', $course->id)->count());
    }
}
