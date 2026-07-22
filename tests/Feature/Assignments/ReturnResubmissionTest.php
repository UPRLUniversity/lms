<?php

namespace Tests\Feature\Assignments;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The return-for-resubmission state machine:
 * submitted → graded → returned → (new version) submitted → graded.
 */
class ReturnResubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private Course $course;

    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = $this->userWithRole(Role::Instructor->value);
        $this->course = Course::factory()->published()->create(['created_by' => $this->instructor->id]);
        $this->assignment = Assignment::factory()->published()->create([
            'course_id' => $this->course->id,
            'created_by' => $this->instructor->id,
            'max_points' => 10,
        ]);

        $this->student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)
            ->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]);
    }

    public function test_returning_requires_a_note(): void
    {
        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
        ]);

        $this->actingAs($this->instructor)
            ->post(route('grading.assignments.return', $submission), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
    }

    public function test_the_full_loop_return_then_resubmit_then_grade_v2(): void
    {
        // v1 handed in.
        $this->actingAs($this->student)->post(route('submissions.store', [$this->course, $this->assignment]), [
            'body' => '<p>First try.</p>',
        ]);
        $v1 = Submission::firstOrFail();

        // Returned with a note.
        $this->actingAs($this->instructor)
            ->post(route('grading.assignments.return', $v1), ['note' => 'Please add citations.'])
            ->assertRedirect(route('grading.assignments.index'));

        $v1->refresh();
        $this->assertSame(SubmissionStatus::ReturnedForResubmission, $v1->status);
        $this->assertSame('Please add citations.', $v1->return_note);
        $this->assertSame($this->instructor->id, $v1->returned_by);

        // The student sees the note and resubmits.
        $this->actingAs($this->student)
            ->get(route('assignments.show', [$this->course, $this->assignment]))
            ->assertOk()
            ->assertSee('Please add citations.')
            ->assertSee('returned for another version');

        $this->actingAs($this->student)->post(route('submissions.store', [$this->course, $this->assignment]), [
            'body' => '<p>Second try, now with citations.</p>',
        ]);

        $v2 = $this->assignment->latestSubmissionFor($this->student);
        $this->assertSame(2, $v2->version);
        $this->assertSame(SubmissionStatus::Submitted, $v2->status);
        // v1 keeps its returned state and note (immutable history).
        $this->assertSame(SubmissionStatus::ReturnedForResubmission, $v1->fresh()->status);

        // Grade v2 → the loop closes.
        $this->actingAs($this->instructor)->put(route('grading.assignments.update', $v2), [
            'points' => 9,
            'feedback' => '<p>Much better.</p>',
        ])->assertRedirect();

        $this->assertSame(SubmissionStatus::Graded, $v2->fresh()->status);
        $this->assertSame('9.00', (string) $v2->fresh()->grade->points_total);
    }

    public function test_returning_a_graded_submission_takes_its_completion_away(): void
    {
        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
        ]);

        $this->actingAs($this->instructor)->put(route('grading.assignments.update', $submission), ['points' => 8]);
        $this->assertTrue($this->assignment->isCompletedBy($this->student));

        $this->actingAs($this->instructor)
            ->post(route('grading.assignments.return', $submission), ['note' => 'Regrading — please revise.']);

        $this->assertFalse($this->assignment->isCompletedBy($this->student));
        // The grade record itself stays for the audit trail.
        $this->assertDatabaseCount('grades', 1);
    }

    public function test_grade_and_next_jumps_to_the_next_waiting_submission(): void
    {
        $submissionA = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
            'submitted_at' => now()->subHour(),
        ]);

        $otherStudent = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)
            ->create(['user_id' => $otherStudent->id, 'course_id' => $this->course->id]);
        $submissionB = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $otherStudent->id,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->instructor)->put(route('grading.assignments.update', $submissionA), [
            'points' => 7,
            'and_next' => 1,
        ])->assertRedirect(route('grading.assignments.show', $submissionB));
    }
}
