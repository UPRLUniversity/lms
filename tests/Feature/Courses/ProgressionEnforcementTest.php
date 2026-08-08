<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseRequirement;
use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\OrderItemKind;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Exceptions\CheckoutException;
use App\Exceptions\EnrollmentException;
use App\Models\Cart;
use App\Models\Course;
use App\Models\CourseGradeRecord;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\OrderFulfilmentService;
use App\Services\Courses\BulkEnrollmentService;
use App\Services\Courses\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every place an enrolment can be created, and what the progression gate does there.
 *
 * The asymmetry IS the design: the gate belongs where the student chooses, not where the
 * system fulfils. Three call sites must deliberately NOT enforce, and those three have
 * tests here for exactly that reason — they are the ones a future change would silently
 * break, and a test that only covered the refusals would not notice.
 *
 *   selfEnroll            refuse
 *   add to cart           refuse          ← the primary gate; every programme course is paid
 *   checkout              refuse
 *   purchase fulfilment   ALLOW           ← money has moved; refusing strands a paid order
 *   admin enrol           ALLOW + record  ← staff may admit an exception, never invisibly
 *   bulk import           ALLOW + flag    ← a staff action; the preview makes it a decision
 */
class ProgressionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Course $blocked;

    private Course $firstPartCourse;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        // A sequential programme: Part I holds one compulsory course, Part II holds the
        // course under test. The student has passed nothing, so Part II is locked.
        $programme = Programme::factory()->sequential()->create();

        $one = ProgrammePart::factory()->named('Part I', 0)->create([
            'programme_id' => $programme->id, 'credit_target' => null,
        ]);
        $two = ProgrammePart::factory()->named('Part II', 1)->create([
            'programme_id' => $programme->id, 'credit_target' => null,
        ]);

        $this->firstPartCourse = $this->place(Course::factory()->published()->create(['is_free' => true]), $one);
        $this->blocked = $this->place(Course::factory()->published()->create(['is_free' => true]), $two);

        $this->student = $this->userWithRole(Role::Student->value);
    }

    private function place(Course $course, ProgrammePart $part): Course
    {
        $part->courses()->attach($course->id, [
            'credit_load' => 12,
            'requirement' => CourseRequirement::Compulsory->value,
            'is_primary' => true,
            'position' => 0,
        ]);

        return $course->fresh();
    }

    private function clearPartOne(): void
    {
        Enrollment::factory()->status(EnrollmentStatus::Completed)->create([
            'user_id' => $this->student->id,
            'course_id' => $this->firstPartCourse->id,
        ]);

        CourseGradeRecord::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->firstPartCourse->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Refuses
    |--------------------------------------------------------------------------
    */

    public function test_self_enrolment_is_refused_and_then_allowed_once_the_earlier_part_is_cleared(): void
    {
        $service = app(EnrollmentService::class);

        try {
            $service->selfEnroll($this->student, $this->blocked);
            $this->fail('Expected the progression gate to refuse.');
        } catch (EnrollmentException $e) {
            $this->assertStringContainsString('Part I', $e->getMessage());
            $this->assertNotNull($e->verdict);
        }

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $this->student->id, 'course_id' => $this->blocked->id,
        ]);

        $this->clearPartOne();

        $enrollment = app(EnrollmentService::class)->selfEnroll($this->student, $this->blocked);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_a_blocked_student_is_refused_rather_than_waitlisted_on_a_full_course(): void
    {
        // Promotion off a waitlist is automatic when a seat frees, so queueing a blocked
        // student would eventually enrol them past the gate with nobody watching.
        $this->blocked->update(['capacity' => 1]);
        Enrollment::factory()->status(EnrollmentStatus::Active)->create(['course_id' => $this->blocked->id]);

        $this->assertTrue($this->blocked->fresh()->isFull());

        $this->expectException(EnrollmentException::class);
        app(EnrollmentService::class)->selfEnroll($this->student, $this->blocked);
    }

    public function test_adding_a_blocked_course_to_the_cart_is_refused(): void
    {
        $this->actingAs($this->student)
            ->post(route('cart.store', $this->blocked))
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'Part I'));

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_a_guest_may_still_fill_a_cart_because_their_history_is_unknowable(): void
    {
        // Refusing a guest would mean demanding an account before anything can be added —
        // the friction the public catalogue exists to remove. Checkout re-checks.
        $this->post(route('cart.store', $this->blocked))->assertRedirect();

        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_checkout_refuses_the_whole_order_and_names_the_offending_course(): void
    {
        $cart = Cart::create(['user_id' => $this->student->id]);
        $cart->items()->create(['course_id' => $this->blocked->id, 'unit_price' => 7000]);

        try {
            app(CheckoutService::class)->place($cart->fresh(), $this->student, 'bank_transfer');
            $this->fail('Expected checkout to refuse a blocked line.');
        } catch (CheckoutException $e) {
            $this->assertStringContainsString($this->blocked->title, $e->getMessage());
            $this->assertStringContainsString('Part I', $e->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Allows — the three that must NOT enforce
    |--------------------------------------------------------------------------
    */

    public function test_a_paid_order_is_always_fulfilled_even_when_the_gate_would_refuse(): void
    {
        // The money has already changed hands. Refusing here would strand a paid order
        // with no access — the failure the cart check exists to prevent before payment.
        $order = Order::create([
            'reference' => 'TEST-ORDER-1',
            'user_id' => $this->student->id,
            'status' => OrderStatus::Pending,
            'subtotal' => 7000, 'discount_total' => 0, 'total' => 7000,
            'currency' => 'NGN', 'payment_method_key' => 'bank_transfer',
        ]);
        $order->items()->create([
            'kind' => OrderItemKind::Course->value,
            'course_id' => $this->blocked->id,
            'title' => $this->blocked->title,
            'unit_price' => 7000, 'line_total' => 7000,
        ]);

        app(OrderFulfilmentService::class)->markPaid($order);

        $enrollment = Enrollment::where('user_id', $this->student->id)
            ->where('course_id', $this->blocked->id)
            ->firstOrFail();

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertSame(EnrollmentSource::Purchase, $enrollment->source);

        // ...and the override is recorded, so a paid enrolment past the gate is visible.
        $this->assertTrue($enrollment->overrodePrerequisites());
        $this->assertStringContainsString('TEST-ORDER-1', $enrollment->prerequisite_override_reason);
    }

    public function test_an_admin_may_enrol_past_the_gate_and_it_is_recorded(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);

        $enrollment = app(EnrollmentService::class)->adminEnroll(
            $this->student,
            $this->blocked,
            $admin,
            overridePrerequisites: true,
            overrideReason: 'Transfer student — prior credit verified.',
        );

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertTrue($enrollment->overrodePrerequisites());
        $this->assertSame($admin->id, $enrollment->prerequisite_override_by);
        $this->assertSame('Transfer student — prior credit verified.', $enrollment->prerequisite_override_reason);
    }

    public function test_admin_enrol_refuses_by_default_so_a_new_caller_inherits_the_safe_behaviour(): void
    {
        // The parameter defaults to FALSE deliberately: a caller added in a year's time
        // gets the enforcing behaviour unless it opts out at its own call site.
        $this->expectException(EnrollmentException::class);

        app(EnrollmentService::class)->adminEnroll(
            $this->student,
            $this->blocked,
            $this->userWithRole(Role::Admin->value),
        );
    }

    public function test_an_ordinary_admin_enrolment_records_no_override(): void
    {
        // Only a genuine block writes the audit columns. Stamping every enrolment would
        // make a non-null value meaningless.
        $enrollment = app(EnrollmentService::class)->adminEnroll(
            $this->student,
            $this->firstPartCourse,
            $this->userWithRole(Role::Admin->value),
            overridePrerequisites: true,
        );

        $this->assertFalse($enrollment->overrodePrerequisites());
        $this->assertNull($enrollment->prerequisite_override_reason);
    }

    public function test_a_bulk_import_flags_the_row_in_the_preview_and_imports_it_anyway(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $csv = "email,course_code\n{$this->student->email},{$this->blocked->code}\n";

        $report = app(BulkEnrollmentService::class)->analyze($csv);

        $this->assertSame(BulkEnrollmentService::PREREQUISITE_NOT_MET, $report['rows'][0]['problem']);
        $this->assertStringContainsString('Part I', $report['rows'][0]['reason']);
        // Flagged, but still counted as importable — the headline number must not promise
        // fewer enrolments than the import performs.
        $this->assertSame(1, $report['counts']['valid']);

        $result = app(BulkEnrollmentService::class)->import($csv, $admin);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['overridden']);

        $enrollment = Enrollment::where('user_id', $this->student->id)
            ->where('course_id', $this->blocked->id)
            ->firstOrFail();

        $this->assertSame(EnrollmentSource::Bulk, $enrollment->source);
        $this->assertTrue($enrollment->overrodePrerequisites());
    }

    /*
    |--------------------------------------------------------------------------
    | Grandfathering
    |--------------------------------------------------------------------------
    */

    public function test_switching_a_programme_to_sequential_never_revokes_access_already_held(): void
    {
        // The gate applies at ENROLMENT time only. An existing enrolment is never
        // re-evaluated — a student mid-way through a course they were legitimately sold
        // must not lose it because an admin changed a setting.
        $programme = Programme::factory()->create();   // open
        $one = ProgrammePart::factory()->named('Part I', 0)->create(['programme_id' => $programme->id, 'credit_target' => null]);
        $two = ProgrammePart::factory()->named('Part II', 1)->create(['programme_id' => $programme->id, 'credit_target' => null]);
        $this->place(Course::factory()->published()->create(), $one);
        $later = $this->place(Course::factory()->published()->create(['is_free' => true]), $two);

        $enrollment = app(EnrollmentService::class)->selfEnroll($this->student, $later);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);

        $programme->update(['progression_rule' => 'sequential']);

        $enrollment = $enrollment->fresh();

        $this->assertSame(
            EnrollmentStatus::Active,
            $enrollment->status,
            'An existing enrolment must survive the programme being switched to sequential.'
        );
        $this->assertTrue($enrollment->grantsLearningAccess(), 'They must still be able to open the course.');

        // But a NEW enrolment in that part is now refused — the rule applies going forward.
        $another = $this->place(Course::factory()->published()->create(['is_free' => true]), $two);
        $this->expectException(EnrollmentException::class);
        app(EnrollmentService::class)->selfEnroll($this->student, $another);
    }
}
