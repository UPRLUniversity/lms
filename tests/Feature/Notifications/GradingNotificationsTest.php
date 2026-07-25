<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\Submission;
use App\Notifications\AssignmentGradedNotification;
use App\Notifications\AssignmentReturnedNotification;
use App\Notifications\AttemptGradedNotification;
use App\Notifications\NewSubmissionNotification;
use App\Services\Assessments\AttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GradingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_an_assignment_notifies_the_courses_instructors(): void
    {
        Notification::fake();

        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->withInstructor($instructor)->create();
        $assignment = Assignment::factory()->published()->create(['course_id' => $course->id]);
        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $this->actingAs($student)->post(route('submissions.store', [$course, $assignment]), [
            'body' => '<p>My answer.</p>',
        ]);

        Notification::assertSentTo($instructor, NewSubmissionNotification::class);
    }

    public function test_grading_a_submission_notifies_the_student(): void
    {
        Notification::fake();

        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        $assignment = Assignment::factory()->published()->create(['course_id' => $course->id, 'max_points' => 10]);
        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);
        $submission = Submission::factory()->create(['assignment_id' => $assignment->id, 'user_id' => $student->id]);

        $this->actingAs($instructor)->put(route('grading.assignments.update', $submission), ['points' => 8]);

        Notification::assertSentTo($student, AssignmentGradedNotification::class);
    }

    public function test_returning_a_submission_notifies_the_student(): void
    {
        Notification::fake();

        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        $assignment = Assignment::factory()->published()->create(['course_id' => $course->id]);
        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);
        $submission = Submission::factory()->create(['assignment_id' => $assignment->id, 'user_id' => $student->id]);

        $this->actingAs($instructor)
            ->post(route('grading.assignments.return', $submission), ['note' => 'Please revise.']);

        Notification::assertSentTo($student, AssignmentReturnedNotification::class);
    }

    public function test_finalising_an_essay_attempt_notifies_the_student(): void
    {
        Notification::fake();

        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->published()->create(['created_by' => $instructor->id]);
        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id, 'passing_score' => 50]);
        $essay = Question::factory()->essay()->points(10)->create(['course_id' => $course->id]);
        $assessment->questions()->attach($essay->id, ['position' => 0]);

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->active()->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $service = app(AttemptService::class);
        $attempt = $service->startAttempt($assessment, $student);
        $service->saveAnswer($attempt, $essay->id, 'A thoughtful answer.');
        $service->submit($attempt);

        $answer = $attempt->answers()->where('question_id', $essay->id)->firstOrFail();
        $this->actingAs($instructor)->put(route('grading.update', $attempt), [
            'grades' => [$answer->id => ['points' => 8]],
        ]);

        Notification::assertSentTo($student, AttemptGradedNotification::class);
    }
}
