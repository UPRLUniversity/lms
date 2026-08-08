<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseRequirement;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Cart;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * What a blocked student actually sees, and what an administrator can do about it.
 *
 * The rule is only useful if the person it stops can tell WHY and what to do next, so
 * every surface here asserts the reason is present — not merely that the buy button
 * disappeared.
 */
class ProgressionUiTest extends TestCase
{
    use RefreshDatabase;

    private Programme $programme;

    private ProgrammePart $partOne;

    private Course $blocked;

    private Course $firstPartCourse;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->programme = Programme::factory()->sequential()->create(['name' => 'Certificate in PR']);

        $this->partOne = ProgrammePart::factory()->named('Part I', 0)->create([
            'programme_id' => $this->programme->id, 'credit_target' => 24,
        ]);
        $partTwo = ProgrammePart::factory()->named('Part II', 1)->create([
            'programme_id' => $this->programme->id, 'credit_target' => null,
        ]);

        $this->firstPartCourse = $this->place(Course::factory()->published()->create(), $this->partOne, 12);
        $this->place(Course::factory()->published()->create(), $this->partOne, 12);
        $this->blocked = $this->place(Course::factory()->published()->create(['is_free' => true]), $partTwo, 12);

        $this->student = $this->userWithRole(Role::Student->value);
    }

    private function place(Course $course, ProgrammePart $part, int $credits): Course
    {
        $part->courses()->attach($course->id, [
            'credit_load' => $credits,
            'requirement' => CourseRequirement::Compulsory->value,
            'is_primary' => true,
            'position' => 0,
        ]);

        return $course->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | The student's side
    |--------------------------------------------------------------------------
    */

    public function test_the_course_page_shows_a_locked_state_naming_the_part_instead_of_the_enrol_button(): void
    {
        $response = $this->actingAs($this->student)->get(route('catalogue.show', $this->blocked));

        $response->assertOk()
            ->assertSee('Complete Certificate in PR · Part I first')
            ->assertSee('compulsory courses are still to pass', false)
            // Locked, not hidden: the part is linked so they can see what they are working
            // toward rather than hitting a dead end.
            ->assertSee('See Part I')
            ->assertDontSee('Enrol — start learning');
    }

    public function test_a_guest_sees_the_ordinary_page_because_their_history_is_unknowable(): void
    {
        $this->get(route('catalogue.show', $this->blocked))
            ->assertOk()
            ->assertDontSee('Complete Certificate in PR · Part I first');
    }

    public function test_the_catalogue_marks_a_blocked_card_as_locked(): void
    {
        $this->actingAs($this->student)->get(route('catalogue.index'))
            ->assertOk()
            ->assertSee('Locked');
    }

    public function test_an_enrolled_student_keeps_their_continue_button_whatever_the_rule_says(): void
    {
        // Grandfathering, seen from the student's side: an existing enrolment is never
        // re-evaluated, so the locked state must not appear over the top of it.
        Enrollment::factory()->status(EnrollmentStatus::Active)->create([
            'user_id' => $this->student->id, 'course_id' => $this->blocked->id,
        ]);

        $this->actingAs($this->student)->get(route('catalogue.show', $this->blocked))
            ->assertOk()
            ->assertSee('Continue learning')
            ->assertDontSee('Complete Certificate in PR · Part I first');
    }

    public function test_the_programme_page_shows_the_ladder_with_both_bars(): void
    {
        // One of Part I's two compulsory papers passed: 1 of 2, and 12 of 24 credits.
        Enrollment::factory()->status(EnrollmentStatus::Completed)->create([
            'user_id' => $this->student->id, 'course_id' => $this->firstPartCourse->id,
        ]);
        CourseGradeRecord::factory()->create([
            'user_id' => $this->student->id, 'course_id' => $this->firstPartCourse->id,
        ]);

        $this->actingAs($this->student)->get(route('programmes.show', $this->programme))
            ->assertOk()
            ->assertSee('1 of 2 compulsory papers passed')
            ->assertSee('12 of 24 credits earned')
            ->assertSee('Locked');
    }

    public function test_the_programme_page_is_unchanged_for_a_guest(): void
    {
        $this->get(route('programmes.show', $this->programme))
            ->assertOk()
            ->assertDontSee('compulsory papers passed');
    }

    public function test_the_cart_flags_a_line_that_became_blocked_rather_than_dropping_it(): void
    {
        // The item is already in the cart (added before the rule changed, say), so the
        // add-time refusal never ran for it.
        $cart = Cart::create(['user_id' => $this->student->id]);
        $cart->items()->create(['course_id' => $this->blocked->id, 'unit_price' => 7000]);

        $this->actingAs($this->student)->get(route('cart.index'))
            ->assertOk()
            ->assertSee($this->blocked->title)          // still there
            ->assertSee('You need to complete', false); // and flagged
    }

    /*
    |--------------------------------------------------------------------------
    | The administrator's side
    |--------------------------------------------------------------------------
    */

    public function test_the_roster_offers_an_override_only_after_showing_the_reason(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        // First attempt: refused, and the reason comes back with it.
        $this->actingAs($admin)
            ->post(route('enrollment.admin.store'), [
                'user_id' => $this->student->id,
                'course_id' => $this->blocked->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionHas('prerequisite_block');

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $this->student->id, 'course_id' => $this->blocked->id,
        ]);

        // Second attempt, with the override ticked and a reason given.
        $this->actingAs($admin)
            ->post(route('enrollment.admin.store'), [
                'user_id' => $this->student->id,
                'course_id' => $this->blocked->id,
                'override_prerequisites' => '1',
                'override_reason' => 'Transfer student — Part I verified.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->blocked->id,
            'prerequisite_override_by' => $admin->id,
            'prerequisite_override_reason' => 'Transfer student — Part I verified.',
        ]);
    }

    public function test_an_override_without_a_reason_is_refused(): void
    {
        // An override nobody can account for later is not an audit trail.
        $this->actingAs($this->userWithRole(Role::Admin->value))
            ->post(route('enrollment.admin.store'), [
                'user_id' => $this->student->id,
                'course_id' => $this->blocked->id,
                'override_prerequisites' => '1',
            ])
            ->assertSessionHasErrors('override_reason');

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $this->student->id, 'course_id' => $this->blocked->id,
        ]);
    }

    public function test_an_admin_can_set_the_progression_rule_and_the_unlock_override(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $this->actingAs($admin)->put(route('admin.programmes.update', $this->programme), [
            'name' => $this->programme->name,
            'code' => $this->programme->code,
            'is_active' => '1',
            'progression_rule' => 'open',
        ])->assertRedirect();

        $this->assertSame('open', $this->programme->fresh()->progression_rule->value);

        $this->actingAs($admin)->put(route('admin.programme-parts.update', $this->partOne), [
            'name' => $this->partOne->name,
            'credit_target' => 24,
            'unlock_credits' => 12,
        ])->assertRedirect();

        $this->assertSame(12, $this->partOne->fresh()->unlock_credits);
    }

    /*
    |--------------------------------------------------------------------------
    | The audit command
    |--------------------------------------------------------------------------
    */

    public function test_the_audit_command_reports_who_would_be_blocked_and_changes_nothing(): void
    {
        // Enrolled in a Part II course with no Part I behind them — exactly the situation
        // an admin needs to see BEFORE switching the rule on.
        Enrollment::factory()->status(EnrollmentStatus::Active)->create([
            'user_id' => $this->student->id, 'course_id' => $this->blocked->id,
        ]);

        // Artisan::call rather than $this->artisan(): the report is a table, and the
        // mocked output style behind expectsOutputToContain does not capture table rows.
        $this->assertSame(0, Artisan::call('progression:audit', ['programme' => $this->programme->code]));
        $output = Artisan::output();

        $this->assertStringContainsString($this->student->name, $output);
        $this->assertStringContainsString($this->blocked->code, $output);
        $this->assertStringContainsString('Part I', $output);
        $this->assertStringContainsString('would be refused if made today', $output);

        // Reports only. The enrolment is untouched.
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->blocked->id,
            'status' => EnrollmentStatus::Active->value,
        ]);
    }

    public function test_the_audit_command_can_preview_an_open_programme_before_the_switch(): void
    {
        $this->programme->update(['progression_rule' => 'open']);

        Enrollment::factory()->status(EnrollmentStatus::Active)->create([
            'user_id' => $this->student->id, 'course_id' => $this->blocked->id,
        ]);

        // The whole point: the question is only worth asking before anybody flips it.
        $this->assertSame(0, Artisan::call('progression:audit', ['programme' => $this->programme->code]));
        $output = Artisan::output();

        $this->assertStringContainsString($this->student->name, $output);
        $this->assertStringContainsString('hypothetical', $output);
    }
}
