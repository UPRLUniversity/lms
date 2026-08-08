<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseRequirement;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use App\Services\Courses\ProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rule itself, with nothing enforcing it yet (Phase 1 of the progression plan).
 *
 * Everything the gate will later depend on is settled here: what "passed" means, which
 * credits count, when a part is cleared, and the four cases that must ALWAYS be allowed —
 * an open programme, the first part, an unplaced course, and a course reachable through a
 * second programme.
 */
class ProgressionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProgressionService $progression;

    protected function setUp(): void
    {
        parent::setUp();
        $this->progression = app(ProgressionService::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    /**
     * A sequential programme with two parts. Part I holds two compulsory 12-credit
     * courses (target 24); Part II holds one.
     *
     * @return array{0: Programme, 1: ProgrammePart, 2: ProgrammePart}
     */
    private function programme(bool $sequential = true, ?int $creditTarget = 24): array
    {
        $programme = Programme::factory()->when($sequential, fn ($f) => $f->sequential())->create();

        $one = ProgrammePart::factory()->named('Part I', 0)->create([
            'programme_id' => $programme->id,
            'credit_target' => $creditTarget,
        ]);
        $two = ProgrammePart::factory()->named('Part II', 1)->create([
            'programme_id' => $programme->id,
            'credit_target' => $creditTarget,
        ]);

        return [$programme, $one, $two];
    }

    private function place(Course $course, ProgrammePart $part, CourseRequirement $requirement, int $credits = 12): Course
    {
        $part->courses()->attach($course->id, [
            'credit_load' => $credits,
            'requirement' => $requirement->value,
            'is_primary' => true,
            'position' => 0,
        ]);

        return $course->fresh();
    }

    private function course(): Course
    {
        return Course::factory()->published()->create();
    }

    /**
     * Complete a course for a student, optionally recording a failing grade.
     */
    private function complete(User $student, Course $course, bool $passed = true, bool $graded = true): void
    {
        Enrollment::factory()->status(EnrollmentStatus::Completed)->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        if (! $graded) {
            return;
        }

        CourseGradeRecord::factory()
            ->when(! $passed, fn ($f) => $f->failed())
            ->create(['user_id' => $student->id, 'course_id' => $course->id]);
    }

    private function student(): User
    {
        return $this->userWithRole(Role::Student->value);
    }

    /*
    |--------------------------------------------------------------------------
    | Always allowed
    |--------------------------------------------------------------------------
    */

    public function test_a_course_in_no_programme_part_is_never_gated(): void
    {
        // Short courses and Master Classes are deliberately standalone.
        $verdict = $this->progression->check($this->student(), $this->course());

        $this->assertTrue($verdict->allowed);
        $this->assertNull($verdict->message());
    }

    public function test_the_first_part_of_a_programme_is_always_open(): void
    {
        [, $one] = $this->programme();
        $course = $this->place($this->course(), $one, CourseRequirement::Compulsory);

        $this->assertTrue($this->progression->check($this->student(), $course)->allowed);
    }

    public function test_an_open_programme_gates_nothing(): void
    {
        [, $one, $two] = $this->programme(sequential: false);
        $this->place($this->course(), $one, CourseRequirement::Compulsory);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $this->assertTrue($this->progression->check($this->student(), $later)->allowed);
    }

    public function test_a_course_placed_in_two_programmes_passes_if_either_is_unlocked(): void
    {
        // Locked in the sequential programme's Part II...
        [, $one, $two] = $this->programme();
        $this->place($this->course(), $one, CourseRequirement::Compulsory);
        $course = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();
        $this->assertFalse($this->progression->check($student, $course)->allowed);

        // ...but also placed in the FIRST part of a second programme, which is always
        // open. A student must never be blocked from a course they are entitled to.
        [, $otherFirst] = $this->programme();
        $this->place($course, $otherFirst, CourseRequirement::Elective);

        $this->assertTrue($this->progression->check($student, $course->fresh())->allowed);
    }

    /*
    |--------------------------------------------------------------------------
    | Bar 1 — compulsory
    |--------------------------------------------------------------------------
    */

    public function test_a_later_part_is_blocked_until_every_compulsory_course_is_passed(): void
    {
        [, $one, $two] = $this->programme(creditTarget: null);
        $first = $this->place($this->course(), $one, CourseRequirement::Compulsory);
        $second = $this->place($this->course(), $one, CourseRequirement::Compulsory);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();

        $verdict = $this->progression->check($student, $later);
        $this->assertFalse($verdict->allowed);
        $this->assertSame(2, $verdict->compulsoryOutstandingCount());
        $this->assertStringContainsString('2 compulsory courses are still to pass', $verdict->message());

        $this->complete($student, $first);
        $this->assertStringContainsString(
            'one compulsory course is still to pass',
            app(ProgressionService::class)->check($student, $later)->message()
        );

        $this->complete($student, $second);
        $this->assertTrue(app(ProgressionService::class)->check($student, $later)->allowed);
    }

    public function test_an_elective_is_never_required_to_unlock_the_next_part(): void
    {
        [, $one, $two] = $this->programme(creditTarget: null);
        $compulsory = $this->place($this->course(), $one, CourseRequirement::Compulsory);
        $this->place($this->course(), $one, CourseRequirement::Elective);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();
        $this->complete($student, $compulsory);

        $this->assertTrue(app(ProgressionService::class)->check($student, $later)->allowed);
    }

    /*
    |--------------------------------------------------------------------------
    | Bar 2 — credits
    |--------------------------------------------------------------------------
    */

    public function test_the_credit_target_is_a_second_bar_on_top_of_the_compulsory_one(): void
    {
        // Part I: one 12-credit compulsory course, and a 12-credit REQUIRED ELECTIVE that
        // counts toward the published 24. Passing only the compulsory one leaves them at
        // 12 of 24 — on track by the compulsory bar, short by the published total.
        [, $one, $two] = $this->programme(creditTarget: 24);
        $compulsory = $this->place($this->course(), $one, CourseRequirement::Compulsory, 12);
        $requiredElective = $this->place($this->course(), $one, CourseRequirement::RequiredElective, 12);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();
        $this->complete($student, $compulsory);

        $verdict = app(ProgressionService::class)->check($student, $later);
        $this->assertFalse($verdict->allowed);
        $this->assertSame(0, $verdict->compulsoryOutstandingCount());
        $this->assertSame(12, $verdict->creditsEarned);
        $this->assertSame(24, $verdict->creditsRequired);
        $this->assertStringContainsString('earned 12 of 24 credits', $verdict->message());

        $this->complete($student, $requiredElective);
        $this->assertTrue(app(ProgressionService::class)->check($student, $later)->allowed);
    }

    public function test_a_part_with_no_credit_target_is_judged_on_the_compulsory_bar_alone(): void
    {
        [, $one, $two] = $this->programme(creditTarget: null);
        $compulsory = $this->place($this->course(), $one, CourseRequirement::Compulsory, 6);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();
        $this->complete($student, $compulsory);

        $this->assertNull($this->progression->creditBarFor($one->fresh()));
        $this->assertTrue(app(ProgressionService::class)->check($student, $later)->allowed);
    }

    public function test_a_pure_elective_does_not_count_toward_the_credit_bar(): void
    {
        // Passing a 24-credit pure elective must NOT clear a 24-credit bar: those credits
        // sit outside the total the prospectus publishes, so counting them here would tell
        // a student they were on track when they were not.
        [, $one, $two] = $this->programme(creditTarget: 24);
        $compulsory = $this->place($this->course(), $one, CourseRequirement::Compulsory, 12);
        $elective = $this->place($this->course(), $one, CourseRequirement::Elective, 24);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();
        $this->complete($student, $compulsory);
        $this->complete($student, $elective);

        $verdict = app(ProgressionService::class)->check($student, $later);
        $this->assertFalse($verdict->allowed);
        $this->assertSame(12, $verdict->creditsEarned);
    }

    public function test_unlock_credits_overrides_the_published_credit_target(): void
    {
        [, $one, $two] = $this->programme(creditTarget: 24);
        $one->update(['unlock_credits' => 12]);

        $compulsory = $this->place($this->course(), $one, CourseRequirement::Compulsory, 12);
        $this->place($this->course(), $one, CourseRequirement::RequiredElective, 12);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();
        $this->complete($student, $compulsory);

        $this->assertSame(12, $this->progression->creditBarFor($one->fresh()));
        $this->assertTrue(app(ProgressionService::class)->check($student, $later)->allowed);
    }

    /*
    |--------------------------------------------------------------------------
    | What counts as "passed"
    |--------------------------------------------------------------------------
    */

    public function test_a_failed_course_does_not_count_as_passed(): void
    {
        [, $one, $two] = $this->programme(creditTarget: null);
        $compulsory = $this->place($this->course(), $one, CourseRequirement::Compulsory);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();
        $this->complete($student, $compulsory, passed: false);

        $verdict = app(ProgressionService::class)->check($student, $later);
        $this->assertFalse($verdict->allowed);
        $this->assertSame(1, $verdict->compulsoryOutstandingCount());
    }

    public function test_an_ungraded_completion_counts_as_passed(): void
    {
        // Some courses carry no assessment at all. Refusing those would gate progression
        // on data that is never going to arrive.
        [, $one, $two] = $this->programme(creditTarget: null);
        $compulsory = $this->place($this->course(), $one, CourseRequirement::Compulsory);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();
        $this->complete($student, $compulsory, graded: false);

        $this->assertTrue(app(ProgressionService::class)->check($student, $later)->allowed);
    }

    public function test_an_active_or_withdrawn_enrolment_is_not_a_pass(): void
    {
        [, $one, $two] = $this->programme(creditTarget: null);
        $first = $this->place($this->course(), $one, CourseRequirement::Compulsory);
        $second = $this->place($this->course(), $one, CourseRequirement::Compulsory);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $student = $this->student();
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['user_id' => $student->id, 'course_id' => $first->id]);
        Enrollment::factory()->status(EnrollmentStatus::Withdrawn)->create(['user_id' => $student->id, 'course_id' => $second->id]);

        $verdict = app(ProgressionService::class)->check($student, $later);
        $this->assertFalse($verdict->allowed);
        $this->assertSame(2, $verdict->compulsoryOutstandingCount());
    }

    /*
    |--------------------------------------------------------------------------
    | Batching + wording
    |--------------------------------------------------------------------------
    */

    public function test_verdicts_for_many_courses_resolve_in_a_constant_number_of_queries(): void
    {
        [, $one, $two] = $this->programme(creditTarget: null);
        $this->place($this->course(), $one, CourseRequirement::Compulsory);

        $courses = collect(range(1, 12))->map(fn () => $this->place($this->course(), $two, CourseRequirement::Compulsory));
        $student = $this->student();

        \DB::enableQueryLog();
        $verdicts = app(ProgressionService::class)->verdictsFor($student, Course::whereIn('id', $courses->pluck('id'))->get());
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertCount(12, $verdicts);
        $this->assertTrue($verdicts->every(fn ($v) => ! $v->allowed));

        // The catalogue renders dozens of cards; the cost must not scale with them.
        $this->assertLessThan(12, $queries, "verdictsFor made {$queries} queries for 12 courses — it is not batching.");
    }

    public function test_the_message_names_only_the_bars_that_are_actually_unmet(): void
    {
        [, $one, $two] = $this->programme(creditTarget: 24);
        $this->place($this->course(), $one, CourseRequirement::Compulsory, 12);
        $this->place($this->course(), $one, CourseRequirement::Compulsory, 12);
        $later = $this->place($this->course(), $two, CourseRequirement::Compulsory);

        $verdict = $this->progression->check($this->student(), $later);

        $this->assertStringContainsString('Part I', $verdict->message());
        $this->assertStringContainsString('2 compulsory courses are still to pass', $verdict->message());
        $this->assertStringContainsString('earned 0 of 24 credits', $verdict->message());
        $this->assertStringContainsString('Part I', $verdict->headline());
    }
}
