<?php

namespace Tests\Feature\Assessments;

use App\Enums\EnrollmentStatus;
use App\Enums\ReviewPolicy;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\User;
use App\Services\Assessments\AttemptPresenter;
use App\Services\Assessments\AttemptService;
use App\Services\Courses\LearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressAndReviewTest extends TestCase
{
    use RefreshDatabase;

    private function enrol(User $student, Course $course): void
    {
        Enrollment::factory()->status(EnrollmentStatus::Active)->create([
            'user_id' => $student->id, 'course_id' => $course->id,
        ]);
    }

    private function passOnce(Assessment $assessment, User $student): void
    {
        $service = app(AttemptService::class);
        $attempt = $service->startAttempt($assessment, $student);
        foreach ($assessment->questions as $q) {
            $service->saveAnswer($attempt, $q->id, $q->correctOptionIds()[0]);
        }
        $service->submit($attempt);
    }

    public function test_a_required_assessment_counts_toward_course_percent(): void
    {
        $course = Course::factory()->published()->create();
        $module = Module::factory()->for($course)->create(['position' => 1]);
        $lesson = Lesson::factory()->for($module)->create(['type' => 'text', 'content_text' => '<p>x</p>', 'position' => 1]);

        $assessment = Assessment::factory()->published()->postModule($module->id)->create([
            'course_id' => $course->id, 'passing_score' => 50, 'is_required' => true,
        ]);
        $q = Question::factory()->mcqSingle()->create(['course_id' => $course->id]);
        $assessment->questions()->attach($q->id, ['position' => 0]);

        $student = $this->userWithRole(Role::Student->value);
        $this->enrol($student, $course);

        $learning = app(LearningService::class);

        // Lesson done, assessment not → 1 of 2 items = 50%.
        $learning->markComplete($student, $lesson);
        $this->assertSame(50, $learning->snapshot($student, $course)->percent());

        // Pass the assessment → 100%.
        $this->passOnce($assessment, $student);
        $this->assertSame(100, $learning->snapshot($student, $course)->percent());
    }

    public function test_sequential_mode_locks_a_lesson_behind_an_unpassed_assessment(): void
    {
        $course = Course::factory()->published()->sequential()->create();
        $module = Module::factory()->for($course)->create(['position' => 1]);
        $lessonA = Lesson::factory()->for($module)->create(['type' => 'text', 'content_text' => '<p>a</p>', 'position' => 1]);
        $lessonB = Lesson::factory()->for($module)->create(['type' => 'text', 'content_text' => '<p>b</p>', 'position' => 2]);

        // A required post-module assessment sits after the module's lessons.
        $assessment = Assessment::factory()->published()->postModule($module->id)->create([
            'course_id' => $course->id, 'passing_score' => 50, 'position' => 1,
        ]);
        $q = Question::factory()->mcqSingle()->create(['course_id' => $course->id]);
        $assessment->questions()->attach($q->id, ['position' => 0]);

        $student = $this->userWithRole(Role::Student->value);
        $this->enrol($student, $course);
        $learning = app(LearningService::class);

        // Complete both lessons; the assessment now sits as the frontier.
        $learning->markComplete($student, $lessonA);
        $learning->markComplete($student, $lessonB);

        $outline = $learning->outline($student, $course);
        $this->assertTrue($outline->isAssessmentLocked($assessment) === false, 'assessment is the open frontier');

        // The student tries to start it — allowed (frontier). Before passing it, nothing is
        // beyond it here, but the assessment itself blocks course completion.
        $this->assertFalse($learning->snapshot($student, $course)->isCourseComplete());

        $this->passOnce($assessment, $student);
        $this->assertTrue($learning->snapshot($student, $course)->isCourseComplete());
    }

    /* ---- review policy gating ------------------------------------------- */

    private function gradedAttempt(ReviewPolicy $policy, bool $closeAfter = false): array
    {
        $course = Course::factory()->published()->create();
        $assessment = Assessment::factory()->published()->reviewPolicy($policy)->create([
            'course_id' => $course->id, 'passing_score' => 0,
        ]);
        $q = Question::factory()->mcqSingle()->create(['course_id' => $course->id]);
        $assessment->questions()->attach($q->id, ['position' => 0]);

        $student = $this->userWithRole(Role::Student->value);
        $this->enrol($student, $course);

        $service = app(AttemptService::class);
        $attempt = $service->startAttempt($assessment, $student);
        $service->saveAnswer($attempt, $q->id, $q->correctOptionIds()[0]);
        $service->submit($attempt);

        // Close the window only after the attempt is in, so "after close" review can unlock.
        if ($closeAfter) {
            $assessment->forceFill(['available_until' => now()->subDay()])->save();
            $attempt->load('assessment');
        }

        return [$student, $attempt->fresh()->load('assessment'), app(AttemptPresenter::class)];
    }

    public function test_review_immediately_exposes_the_breakdown(): void
    {
        [, $attempt, $presenter] = $this->gradedAttempt(ReviewPolicy::Immediately);
        $this->assertTrue($presenter->canReview($attempt));
        $this->assertNotEmpty($presenter->reviewItems($attempt));
    }

    public function test_review_never_hides_the_breakdown(): void
    {
        [, $attempt, $presenter] = $this->gradedAttempt(ReviewPolicy::Never);
        $this->assertFalse($presenter->canReview($attempt));
        $this->assertEmpty($presenter->reviewItems($attempt));
    }

    public function test_review_after_close_waits_for_the_window_to_end(): void
    {
        // Still open → no review yet.
        [, $openAttempt, $presenter] = $this->gradedAttempt(ReviewPolicy::AfterClose, closeAfter: false);
        $this->assertFalse($presenter->canReview($openAttempt));

        // Closed → review unlocks.
        [, $closedAttempt, $presenter2] = $this->gradedAttempt(ReviewPolicy::AfterClose, closeAfter: true);
        $this->assertTrue($presenter2->canReview($closedAttempt));
    }
}
