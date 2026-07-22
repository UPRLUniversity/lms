<?php

namespace Tests\Feature\Assignments;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Submission;
use App\Models\User;
use App\Services\Assignments\AssignmentGradingService;
use App\Services\Courses\LearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Graded required assignments count toward the course % exactly like lessons.
 */
class AssignmentProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private Course $course;

    private Lesson $lesson;

    private Assignment $assignment;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = $this->userWithRole(Role::Instructor->value);
        $this->course = Course::factory()->published()->create(['created_by' => $this->instructor->id]);
        $module = Module::factory()->create(['course_id' => $this->course->id]);
        $this->lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $this->assignment = Assignment::factory()->published()->create([
            'course_id' => $this->course->id,
            'module_id' => $module->id,
            'max_points' => 10,
        ]);

        $this->student = $this->userWithRole(Role::Student->value);
        $this->enrollment = Enrollment::factory()->status(EnrollmentStatus::Active)
            ->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]);
    }

    public function test_a_required_assignment_counts_toward_the_percentage_once_graded(): void
    {
        $learning = app(LearningService::class);

        // 1 lesson + 1 required assignment → completing the lesson alone is 50%.
        $learning->markComplete($this->student, $this->lesson);
        $this->assertSame(50, (int) $this->enrollment->fresh()->progress_percent);

        // Submit + grade → 100% and the enrollment flips to Completed.
        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
        ]);
        app(AssignmentGradingService::class)->grade($submission, $this->instructor, ['points' => 8]);

        $enrollment = $this->enrollment->fresh();
        $this->assertSame(100, (int) $enrollment->progress_percent);
        $this->assertSame(EnrollmentStatus::Completed, $enrollment->status);

        // Returning the graded version drops the course back below 100%.
        app(AssignmentGradingService::class)->returnForResubmission($submission->fresh(), $this->instructor, 'Revise please.');

        $enrollment = $this->enrollment->fresh();
        $this->assertSame(50, (int) $enrollment->progress_percent);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_optional_and_draft_assignments_do_not_count(): void
    {
        $learning = app(LearningService::class);

        // Make the required assignment optional → only the lesson counts.
        $this->assignment->forceFill(['is_required' => false])->save();
        $learning->markComplete($this->student, $this->lesson);
        $this->assertSame(100, (int) $this->enrollment->fresh()->progress_percent);

        // A draft required assignment is invisible to progress too.
        Assignment::factory()->create(['course_id' => $this->course->id, 'is_required' => true]);
        $learning->recalculate($this->student, $this->course->fresh());
        $this->assertSame(100, (int) $this->enrollment->fresh()->progress_percent);
    }

    public function test_the_assignment_appears_in_the_player_outline_and_sidebar(): void
    {
        $outline = app(LearningService::class)->outline($this->student, $this->course);

        $item = $outline->items->first(fn ($i) => $i->isAssignment());
        $this->assertNotNull($item);
        $this->assertSame($this->assignment->id, $item->id());
        $this->assertFalse($item->completed);

        // The player sidebar shows it.
        $this->actingAs($this->student)
            ->get(route('learn.show', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertSee($this->assignment->title);
    }

    public function test_learning_history_gains_an_assignments_column_with_the_score(): void
    {
        $submission = Submission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'user_id' => $this->student->id,
        ]);
        app(AssignmentGradingService::class)->grade($submission, $this->instructor, ['points' => 7.5]);

        $this->actingAs($this->student)->get(route('learning.history'))
            ->assertOk()
            ->assertSee('Assignments')
            ->assertSee('7.5/10');
    }
}
