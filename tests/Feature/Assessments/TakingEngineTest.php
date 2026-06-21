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

class TakingEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A published assessment on a published course, with $n fixed MCQ questions, plus an
     * actively-enrolled student.
     *
     * @return array{0: User, 1: Course, 2: Assessment, 3: \Illuminate\Support\Collection}
     */
    private function scenario(int $n = 3, array $assessmentAttrs = []): array
    {
        $course = Course::factory()->published()->create();
        $assessment = Assessment::factory()->published()->create(array_merge(['course_id' => $course->id], $assessmentAttrs));

        $questions = collect();
        for ($i = 0; $i < $n; $i++) {
            $q = Question::factory()->mcqSingle()->create(['course_id' => $course->id]);
            $assessment->questions()->attach($q->id, ['position' => $i]);
            $questions->push($q);
        }

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create([
            'user_id' => $student->id, 'course_id' => $course->id,
        ]);

        return [$student, $course, $assessment, $questions];
    }

    private function service(): AttemptService
    {
        return app(AttemptService::class);
    }

    public function test_starting_freezes_a_layout_and_autosave_survives_a_refresh(): void
    {
        [$student, $course, $assessment, $questions] = $this->scenario(3, ['shuffle_questions' => true, 'shuffle_options' => true]);

        $this->actingAs($student)->post(route('attempts.store', [$course, $assessment]))->assertRedirect();

        $attempt = Attempt::where('user_id', $student->id)->firstOrFail();
        $frozenOrder = $attempt->questionIds();
        $this->assertCount(3, $frozenOrder);

        // Answer the first question.
        $first = $questions->firstWhere('id', $frozenOrder[0]);
        $this->actingAs($student)->postJson(route('attempts.answer', $attempt), [
            'question_id' => $first->id,
            'response' => $first->correctOptionIds()[0],
        ])->assertOk();

        // "Refresh": re-open the take screen. Order is unchanged and the answer persists.
        $this->actingAs($student)->get(route('attempts.show', $attempt))->assertOk();
        $this->assertSame($frozenOrder, $attempt->fresh()->questionIds(), 'a refresh must not reshuffle');
        $this->assertDatabaseHas('attempt_answers', [
            'attempt_id' => $attempt->id, 'question_id' => $first->id,
        ]);
    }

    public function test_objective_attempt_is_auto_graded_on_submit(): void
    {
        [$student, $course, $assessment, $questions] = $this->scenario(3);

        $attempt = $this->service()->startAttempt($assessment, $student);

        // Answer two of three correctly.
        foreach ($questions->take(2) as $q) {
            $this->service()->saveAnswer($attempt, $q->id, $q->correctOptionIds()[0]);
        }
        $this->service()->saveAnswer($attempt, $questions[2]->id, 'wrong');

        $this->actingAs($student)->postJson(route('attempts.submit', $attempt))->assertOk();

        $attempt->refresh();
        $this->assertSame(AttemptStatus::Graded, $attempt->status);
        $this->assertSame(67, $attempt->percentage); // 2/3 floored→67 via round(66.66)
        $this->assertFalse($attempt->passed); // default pass 70%
    }

    public function test_fill_blank_accepts_listed_variants_case_insensitively(): void
    {
        $course = Course::factory()->published()->create();
        $assessment = Assessment::factory()->published()->create(['course_id' => $course->id, 'passing_score' => 50]);
        $q = Question::factory()->fillBlank(['Paris'], true)->create(['course_id' => $course->id]);
        $assessment->questions()->attach($q->id, ['position' => 0]);

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $course->id]);

        $attempt = $this->service()->startAttempt($assessment, $student);
        $this->service()->saveAnswer($attempt, $q->id, '  pArIs ');
        $this->service()->submit($attempt);

        $this->assertSame(100, $attempt->fresh()->percentage);
    }

    public function test_third_attempt_of_max_two_is_blocked_server_side(): void
    {
        [$student, $course, $assessment] = $this->scenario(2, ['max_attempts' => 2]);

        // Use both attempts (graded).
        Attempt::factory()->graded()->create(['assessment_id' => $assessment->id, 'user_id' => $student->id, 'attempt_number' => 1]);
        Attempt::factory()->graded()->create(['assessment_id' => $assessment->id, 'user_id' => $student->id, 'attempt_number' => 2]);

        $this->actingAs($student)->post(route('attempts.store', [$course, $assessment]))
            ->assertRedirect(route('assessments.start', [$course, $assessment]))
            ->assertSessionHas('error');

        $this->assertSame(2, Attempt::where('user_id', $student->id)->count(), 'no third attempt is created');

        // And the service itself refuses.
        $this->expectException(\DomainException::class);
        $this->service()->startAttempt($assessment, $student);
    }

    public function test_starting_outside_the_window_is_blocked(): void
    {
        [$student, $course, $assessment] = $this->scenario(2, [
            'available_from' => now()->addDay(),
        ]);

        $this->actingAs($student)->post(route('attempts.store', [$course, $assessment]))
            ->assertSessionHas('error');

        $this->assertSame(0, Attempt::where('user_id', $student->id)->count());
    }

    public function test_a_timed_out_attempt_auto_submits_on_load(): void
    {
        [$student, $course, $assessment, $questions] = $this->scenario(2, ['time_limit_minutes' => 30]);

        $attempt = $this->service()->startAttempt($assessment, $student);
        // Force the deadline into the past.
        $attempt->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($student)->get(route('attempts.show', $attempt))
            ->assertRedirect(route('attempts.result', $attempt));

        $this->assertNotSame(AttemptStatus::InProgress, $attempt->fresh()->status, 'expired attempt is submitted');
    }

    public function test_answering_a_question_not_in_the_attempt_is_rejected(): void
    {
        [$student, $course, $assessment] = $this->scenario(2);
        $attempt = $this->service()->startAttempt($assessment, $student);

        // A question from another course — never part of this frozen layout.
        $foreign = Question::factory()->mcqSingle()->create();

        $this->actingAs($student)->postJson(route('attempts.answer', $attempt), [
            'question_id' => $foreign->id, 'response' => 'o1',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('attempt_answers', ['attempt_id' => $attempt->id, 'question_id' => $foreign->id]);
    }

    public function test_a_student_cannot_touch_another_students_attempt(): void
    {
        [$student, $course, $assessment, $questions] = $this->scenario(2);
        $attempt = $this->service()->startAttempt($assessment, $student);

        $other = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $other->id, 'course_id' => $course->id]);

        $this->actingAs($other)->postJson(route('attempts.answer', $attempt), [
            'question_id' => $questions[0]->id, 'response' => 'x',
        ])->assertForbidden();

        $this->actingAs($other)->get(route('attempts.show', $attempt))->assertForbidden();
    }

    public function test_correct_answers_are_never_serialised_to_the_take_screen(): void
    {
        [$student, $course, $assessment, $questions] = $this->scenario(2);
        $attempt = $this->service()->startAttempt($assessment, $student);

        $html = $this->actingAs($student)->get(route('attempts.show', $attempt))->getContent();

        $this->assertStringNotContainsString('is_correct', $html);
        $this->assertStringNotContainsString('"accepted"', $html);
    }
}
