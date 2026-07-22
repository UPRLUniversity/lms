<?php

namespace Tests\Feature\Assignments;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricGradingTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private Course $course;

    private Rubric $rubric;

    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = $this->userWithRole(Role::Instructor->value);
        $this->course = Course::factory()->published()->create(['created_by' => $this->instructor->id]);

        $this->rubric = Rubric::factory()->create(['created_by' => $this->instructor->id]);
        // Two criteria: 10/7/3 and 5/3/1 → best possible 15.
        RubricCriterion::factory()->create(['rubric_id' => $this->rubric->id, 'position' => 0]);
        RubricCriterion::factory()->levels([
            ['label' => 'Sharp', 'points' => 5],
            ['label' => 'OK', 'points' => 3],
            ['label' => 'Weak', 'points' => 1],
        ])->create(['rubric_id' => $this->rubric->id, 'position' => 1]);

        $this->assignment = Assignment::factory()->published()->create([
            'course_id' => $this->course->id,
            'created_by' => $this->instructor->id,
            'rubric_id' => $this->rubric->id,
            'max_points' => 15,
        ]);
    }

    private function submissionFromStudent(): Submission
    {
        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)
            ->create(['user_id' => $student->id, 'course_id' => $this->course->id]);

        return Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_the_server_recomputes_the_rubric_total_from_level_choices(): void
    {
        $submission = $this->submissionFromStudent();
        [$first, $second] = $this->rubric->criteria->all();

        $this->actingAs($this->instructor)->put(route('grading.assignments.update', $submission), [
            'criteria' => [
                $first->id => 1,  // Good → 7
                $second->id => 0, // Sharp → 5
            ],
            'feedback' => '<p>Well argued; tighten the sourcing.</p>',
        ])->assertRedirect(route('grading.assignments.index'));

        $submission->refresh();
        $this->assertSame(SubmissionStatus::Graded, $submission->status);
        $this->assertSame('12.00', (string) $submission->grade->points_total);

        // The snapshot preserves the breakdown exactly as graded.
        $scores = collect($submission->grade->criterion_scores);
        $this->assertSame(['Good', 'Sharp'], $scores->pluck('level_label')->all());
        $this->assertSame([7.0, 5.0], $scores->pluck('points')->map(fn ($p) => (float) $p)->all());
    }

    public function test_every_criterion_must_be_scored_and_levels_must_exist(): void
    {
        $submission = $this->submissionFromStudent();
        [$first, $second] = $this->rubric->criteria->all();

        // Missing second criterion.
        $this->actingAs($this->instructor)->put(route('grading.assignments.update', $submission), [
            'criteria' => [$first->id => 0],
        ])->assertSessionHasErrors('criteria');

        // Level index off the grid.
        $this->actingAs($this->instructor)->put(route('grading.assignments.update', $submission), [
            'criteria' => [$first->id => 0, $second->id => 9],
        ])->assertSessionHasErrors('criteria');

        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->status);
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_rubric_free_grading_caps_points_at_max(): void
    {
        $plain = Assignment::factory()->published()->create([
            'course_id' => $this->course->id,
            'created_by' => $this->instructor->id,
            'max_points' => 20,
        ]);
        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)
            ->create(['user_id' => $student->id, 'course_id' => $this->course->id]);
        $submission = Submission::factory()->create(['assignment_id' => $plain->id, 'user_id' => $student->id]);

        // A tampered 999 is capped server-side at max_points.
        $this->actingAs($this->instructor)->put(route('grading.assignments.update', $submission), [
            'points' => 999,
        ])->assertRedirect();

        $this->assertSame('20.00', (string) $submission->fresh()->grade->points_total);
    }

    public function test_the_student_sees_the_breakdown_and_feedback_exactly_as_graded(): void
    {
        $submission = $this->submissionFromStudent();
        [$first, $second] = $this->rubric->criteria->all();

        $this->actingAs($this->instructor)->put(route('grading.assignments.update', $submission), [
            'criteria' => [$first->id => 0, $second->id => 2],
            'feedback' => '<p>Excellent structure throughout.</p>',
        ]);

        $this->actingAs($submission->user)
            ->get(route('assignments.show', [$this->course, $this->assignment]))
            ->assertOk()
            ->assertSee('Excellent structure throughout.')
            ->assertSee($first->title)
            ->assertSee('11'); // 10 + 1
    }

    public function test_grading_authorization_is_scoped_to_the_course(): void
    {
        $submission = $this->submissionFromStudent();
        [$first, $second] = $this->rubric->criteria->all();

        $outsider = $this->userWithRole(Role::Instructor->value);
        $this->actingAs($outsider)->put(route('grading.assignments.update', $submission), [
            'criteria' => [$first->id => 0, $second->id => 0],
        ])->assertForbidden();

        // Auditor can look at the workspace but not grade.
        $auditor = $this->userWithRole(Role::Auditor->value);
        $this->actingAs($auditor)->get(route('grading.assignments.show', $submission))->assertOk();
        $this->actingAs($auditor)->put(route('grading.assignments.update', $submission), [
            'criteria' => [$first->id => 0, $second->id => 0],
        ])->assertForbidden();
    }
}
