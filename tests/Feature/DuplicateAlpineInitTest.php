<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\Attempt;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alpine calls a data object's own `init()` during initialisation, so an additional
 * `x-init="init()"` on the same element runs it a SECOND time.
 *
 * That is not a cosmetic duplication. On the timed-assessment runner it started a second
 * one-second interval against the same countdown — the clock ran at exactly double speed
 * and a 20-minute exam auto-submitted after 10 (measured in Chrome; see docs/decisions.md).
 * On the two builders it bound duplicate Sortable instances and duplicate video listeners.
 *
 * These assertions are deliberately about the rendered markup rather than behaviour: the
 * damage is done by an attribute that is trivial to reintroduce by copy-paste, and no
 * PHP-level assertion about the page's behaviour would catch it.
 */
class DuplicateAlpineInitTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_timed_attempt_runner_does_not_double_initialise(): void
    {
        $course = Course::factory()->published()->create();
        $assessment = Assessment::factory()->published()->create([
            'course_id' => $course->id,
            'time_limit_minutes' => 20,
        ]);
        $question = Question::factory()->mcqSingle()->create(['course_id' => $course->id]);
        $assessment->questions()->attach($question->id, ['position' => 0]);

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create([
            'user_id' => $student->id, 'course_id' => $course->id,
        ]);

        $this->actingAs($student)->post(route('attempts.store', [$course, $assessment]))->assertRedirect();
        $attempt = Attempt::where('user_id', $student->id)->firstOrFail();

        $this->actingAs($student)->get(route('attempts.show', $attempt))
            ->assertOk()
            ->assertSee('attemptRunner(', false)
            ->assertDontSee('x-init="init()"', false);
    }

    public function test_the_assessment_builder_does_not_double_initialise(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'created_by' => $instructor->id,
        ]);

        $this->actingAs($instructor)->get(route('assessments.edit', [$course, $assessment]))
            ->assertOk()
            ->assertSee('assessmentBuilder(', false)
            ->assertDontSee('x-init="init()"', false);
    }

    public function test_the_lesson_player_does_not_double_initialise(): void
    {
        $course = Course::factory()->published()->create();
        $module = Module::factory()->for($course)->create(['position' => 1]);
        $lesson = Lesson::factory()->for($module)->create(['position' => 1]);

        $student = $this->userWithRole(Role::Student->value);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create([
            'user_id' => $student->id, 'course_id' => $course->id,
        ]);

        $this->actingAs($student)->get(route('learn.show', [$course, $lesson]))
            ->assertOk()
            ->assertSee('learnPlayer(', false)
            ->assertDontSee('x-init="init()"', false);
    }

    public function test_the_course_builder_does_not_double_initialise(): void
    {
        $instructor = $this->userWithRole(Role::Instructor->value);
        $course = Course::factory()->withInstructor($instructor)->create(['created_by' => $instructor->id]);

        $this->actingAs($instructor)->get(route('courses.edit', $course))
            ->assertOk()
            ->assertSee('courseBuilder(', false)
            ->assertDontSee('x-init="init()"', false);
    }
}
