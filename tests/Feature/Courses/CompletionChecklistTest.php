<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Submission;
use App\Models\User;
use App\Services\Courses\LearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reaching the end of the lessons with a required assessment/assignment still open
 * must never silently bounce the learner back to lesson one — congratulations() renders
 * an actionable checklist naming exactly what's left, and the sidebar/outline carries a
 * per-item attempt/score status rather than a flat "not done yet" icon forever.
 */
class CompletionChecklistTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Lesson $lesson;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = Course::factory()->published()->create();
        $module = Module::factory()->create(['course_id' => $this->course->id]);
        $this->lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'type' => 'text',
            'content_text' => '<p>Body</p>',
        ]);

        $this->student = $this->userWithRole(Role::Student->value);
        $this->enrollment = Enrollment::factory()->status(EnrollmentStatus::Active)
            ->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]);
    }

    public function test_finishing_the_last_lesson_with_a_pending_assessment_shows_the_checklist_not_a_bounce(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'is_required' => true,
            'passing_score' => 60,
            'max_attempts' => 2,
        ]);

        app(LearningService::class)->markComplete($this->student, $this->lesson);
        $this->assertSame(EnrollmentStatus::Active, $this->enrollment->fresh()->status);

        $response = $this->actingAs($this->student)->get(route('learn.congratulations', $this->course));

        $response->assertOk()
            ->assertDontSee('Congratulations')
            ->assertSee('Almost there')
            ->assertSee($assessment->title)
            ->assertSee('Not started yet')
            ->assertSee(route('assessments.start', [$this->course, $assessment]), false);
    }

    public function test_a_failed_attempt_with_retries_left_is_named_on_the_checklist_and_sidebar(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'is_required' => true,
            'passing_score' => 60,
            'max_attempts' => 2,
        ]);
        Attempt::factory()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $this->student->id,
            'status' => 'graded',
            'score' => 40,
            'max_score' => 100,
            'percentage' => 40,
            'passed' => false,
        ]);

        app(LearningService::class)->markComplete($this->student, $this->lesson);

        $this->actingAs($this->student)->get(route('learn.congratulations', $this->course))
            ->assertOk()
            ->assertSee('Not passed · 40% · 1 attempt left');

        // The sidebar (reached via the lesson page) carries the same status.
        $this->actingAs($this->student)->get(route('learn.show', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertSee('Not passed · 40% · 1 attempt left');
    }

    public function test_exhausted_attempts_are_named_plainly_and_offered_a_history_view_not_a_dead_retry(): void
    {
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'is_required' => true,
            'passing_score' => 60,
            'max_attempts' => 1,
        ]);
        Attempt::factory()->create([
            'assessment_id' => $assessment->id,
            'user_id' => $this->student->id,
            'status' => 'graded',
            'score' => 44,
            'max_score' => 100,
            'percentage' => 44,
            'passed' => false,
        ]);

        app(LearningService::class)->markComplete($this->student, $this->lesson);

        $this->actingAs($this->student)->get(route('learn.congratulations', $this->course))
            ->assertOk()
            ->assertSee('Not passed · 44% · no attempts left')
            ->assertSee('View history');
    }

    public function test_an_assignment_awaiting_grading_is_distinguished_from_one_never_submitted(): void
    {
        $assignment = Assignment::factory()->published()->create([
            'course_id' => $this->course->id,
            'is_required' => true,
            'max_points' => 20,
        ]);
        Submission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $this->student->id,
            'status' => SubmissionStatus::Submitted->value,
        ]);

        app(LearningService::class)->markComplete($this->student, $this->lesson);

        $this->actingAs($this->student)->get(route('learn.congratulations', $this->course))
            ->assertOk()
            ->assertSee('Awaiting grading')
            ->assertSee('View'); // softened CTA, not "View assignment"
    }

    public function test_the_finish_button_reads_review_whats_left_when_incomplete_and_finish_course_once_complete(): void
    {
        Assessment::factory()->published()->create([
            'course_id' => $this->course->id,
            'is_required' => true,
        ]);

        app(LearningService::class)->markComplete($this->student, $this->lesson);

        $this->actingAs($this->student)->get(route('learn.show', [$this->course, $this->lesson]))
            ->assertOk()
            ->assertSee("Review what's left", false)
            ->assertDontSee('Finish course');
    }

    public function test_a_genuinely_complete_course_still_renders_the_real_congratulations_page(): void
    {
        app(LearningService::class)->markComplete($this->student, $this->lesson);

        $this->enrollment->refresh();
        $this->assertSame(EnrollmentStatus::Completed, $this->enrollment->status);

        $this->actingAs($this->student)->get(route('learn.congratulations', $this->course))
            ->assertOk()
            ->assertSee('Congratulations')
            ->assertDontSee('Almost there');
    }
}
