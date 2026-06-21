<?php

namespace Tests\Feature\Assessments;

use App\Enums\AttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\User;
use App\Services\Assessments\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(Course $course): User
    {
        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);

        return $student;
    }

    public function test_full_essay_flow_submit_grade_then_student_sees_result(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id, 'passing_score' => 50]);
        $essay = Question::factory()->essay()->points(10)->create(['course_id' => $course->id]);
        $assessment->questions()->attach($essay->id, ['position' => 0]);

        $student = $this->enrolledStudent($course);
        $service = app(AttemptService::class);

        // Student submits — the attempt waits for grading (not yet scored).
        $attempt = $service->startAttempt($assessment, $student);
        $service->saveAnswer($attempt, $essay->id, 'A thoughtful, structured argument.');
        $service->submit($attempt);

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $this->assertNull($attempt->percentage);

        // It appears in the instructor's queue.
        $this->actingAs($instructor)->get(route('grading.index'))->assertOk()->assertSee($assessment->title);
        $this->actingAs($instructor)->get(route('grading.show', $attempt))->assertOk();

        // Instructor grades it 8/10 with feedback → finalised, passed.
        $answer = $attempt->answers()->where('question_id', $essay->id)->firstOrFail();
        $this->actingAs($instructor)->put(route('grading.update', $attempt), [
            'grades' => [$answer->id => ['points' => 8, 'feedback' => 'Strong thesis.']],
        ])->assertRedirect(route('grading.index'));

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertSame(80, $attempt->percentage);
        $this->assertTrue($attempt->passed);

        $this->assertDatabaseHas('attempt_answers', [
            'id' => $answer->id, 'points_awarded' => 8.00, 'graded_by' => $instructor->id,
        ]);

        // The student now sees the graded result + feedback.
        $this->actingAs($student)->get(route('attempts.result', $attempt))
            ->assertOk()->assertSee('Strong thesis.');
    }

    public function test_mixed_attempt_is_not_finalised_until_the_essay_is_graded(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id, 'passing_score' => 50]);

        $mcq = Question::factory()->mcqSingle()->points(5)->create(['course_id' => $course->id]);
        $essay = Question::factory()->essay()->points(5)->create(['course_id' => $course->id]);
        $assessment->questions()->attach([$mcq->id => ['position' => 0], $essay->id => ['position' => 1]]);

        $student = $this->enrolledStudent($course);
        $service = app(AttemptService::class);

        $attempt = $service->startAttempt($assessment, $student);
        $service->saveAnswer($attempt, $mcq->id, $mcq->correctOptionIds()[0]);
        $service->saveAnswer($attempt, $essay->id, 'My answer');
        $service->submit($attempt);

        // Objective part scored, essay pending → still 'submitted'.
        $attempt->refresh();
        $this->assertSame(AttemptStatus::Submitted, $attempt->status);
        $mcqAnswer = $attempt->answers()->where('question_id', $mcq->id)->first();
        $this->assertSame('5.00', (string) $mcqAnswer->points_awarded);

        // Grade the essay full marks → 10/10 = 100%.
        $essayAnswer = $attempt->answers()->where('question_id', $essay->id)->first();
        app(AttemptService::class); // noop, clarity
        $this->actingAs($instructor)->put(route('grading.update', $attempt), [
            'grades' => [$essayAnswer->id => ['points' => 5]],
        ])->assertRedirect();

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertSame(100, $attempt->percentage);
    }

    public function test_an_instructor_cannot_grade_another_courses_attempt(): void
    {
        $owner = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $owner->id]);
        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id]);
        $essay = Question::factory()->essay()->create(['course_id' => $course->id]);
        $assessment->questions()->attach($essay->id, ['position' => 0]);

        $student = $this->enrolledStudent($course);
        $service = app(AttemptService::class);
        $attempt = $service->startAttempt($assessment, $student);
        $service->saveAnswer($attempt, $essay->id, 'x');
        $service->submit($attempt);

        $outsider = $this->userWithRole(Role::Instructor->value);
        $this->actingAs($outsider)->get(route('grading.show', $attempt))->assertForbidden();
    }
}
