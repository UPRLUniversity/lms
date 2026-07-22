<?php

namespace Tests\Feature\Assignments;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The exact late matrix:
 *   before due_at            → accepted, not late (regardless of allow_late)
 *   after due_at, allow=false → blocked with a kind message
 *   after due_at, allow=true  → accepted, badged LATE
 *   no due_at                → never late
 */
class LateSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->course = Course::factory()->published()->create();
        $this->student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)
            ->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]);
    }

    private function submit(Assignment $assignment)
    {
        return $this->actingAs($this->student)
            ->from(route('assignments.show', [$this->course, $assignment]))
            ->post(route('submissions.store', [$this->course, $assignment]), [
                'body' => '<p>My work.</p>',
            ]);
    }

    public function test_before_the_deadline_is_accepted_and_not_late(): void
    {
        $assignment = Assignment::factory()->published()
            ->dueAt(now()->addDay())->create(['course_id' => $this->course->id]);

        $this->submit($assignment)->assertRedirect();
        $this->assertFalse(Submission::firstOrFail()->is_late);
    }

    public function test_no_due_date_is_never_late(): void
    {
        $assignment = Assignment::factory()->published()->create(['course_id' => $this->course->id]);

        $this->submit($assignment)->assertRedirect();
        $this->assertFalse(Submission::firstOrFail()->is_late);
    }

    public function test_after_the_deadline_without_allow_late_is_blocked_kindly(): void
    {
        $assignment = Assignment::factory()->published()
            ->dueAt(now()->subHour())->create(['course_id' => $this->course->id]);

        $this->submit($assignment)->assertSessionHasErrors('submission');

        $errors = session('errors')->get('submission');
        $this->assertStringContainsString('deadline', $errors[0]);
        $this->assertStringContainsString('contact your instructor', $errors[0]);
        $this->assertDatabaseCount('submissions', 0);

        // The page explains it too, instead of showing a submit form.
        $this->actingAs($this->student)
            ->get(route('assignments.show', [$this->course, $assignment]))
            ->assertOk()
            ->assertSee('Submissions have closed');
    }

    public function test_after_the_deadline_with_allow_late_is_accepted_and_badged(): void
    {
        $assignment = Assignment::factory()->published()->allowLate()
            ->dueAt(now()->subHour())->create(['course_id' => $this->course->id]);

        $this->submit($assignment)->assertRedirect();

        $submission = Submission::firstOrFail();
        $this->assertTrue($submission->is_late);

        // Badged LATE in the version history.
        $this->actingAs($this->student)
            ->get(route('assignments.show', [$this->course, $assignment]))
            ->assertOk()
            ->assertSee('Late');
    }

    public function test_late_flag_is_per_version_so_an_on_time_v1_keeps_its_state(): void
    {
        $assignment = Assignment::factory()->published()->allowLate()
            ->dueAt(now()->addHour())->create(['course_id' => $this->course->id]);

        $this->submit($assignment)->assertRedirect(); // on time

        $this->travel(2)->hours(); // now past due

        $this->submit($assignment)->assertRedirect(); // late v2

        $versions = $assignment->submissionsFor($this->student);
        $this->assertFalse($versions->firstWhere('version', 1)->is_late);
        $this->assertTrue($versions->firstWhere('version', 2)->is_late);
    }
}
