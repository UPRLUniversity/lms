<?php

namespace Tests\Feature\Commerce;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Exceptions\EnrollmentException;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use App\Services\Commerce\OrderFulfilmentService;
use App\Services\Courses\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * THE PAYWALL.
 *
 * EnrollmentService::selfEnroll is the only place a paid course is protected. Every
 * button, badge and cart page is presentation on top of this one rule, so these are
 * the tests that actually matter: a student posting straight at the enrol endpoint
 * must be refused, and a student who has paid must get in.
 */
class PaidEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function paidCourse(): Course
    {
        $programme = Programme::factory()->create(['per_paper_fee' => 7000]);
        $course = Course::factory()->published()->create(['is_free' => false]);
        $course->programmeParts()->attach(
            ProgrammePart::factory()->for($programme)->create()->id,
            ['is_primary' => true],
        );

        return $course->load('programmeParts.programme');
    }

    private function markPurchased(User $user, Course $course): Order
    {
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);
        OrderItem::factory()->forCourse($course)->create(['order_id' => $order->id]);

        return $order;
    }

    public function test_self_enrolling_on_a_paid_course_is_refused_without_a_paid_order(): void
    {
        $student = $this->userWithRole('student');
        $course = $this->paidCourse();

        $this->expectException(EnrollmentException::class);
        $this->expectExceptionMessage('This course must be purchased before you can enrol.');

        app(EnrollmentService::class)->selfEnroll($student, $course);
    }

    public function test_posting_directly_at_the_enrol_endpoint_does_not_bypass_the_paywall(): void
    {
        // The button is never rendered for a paid course, so this is the attack: a
        // student who knows the URL.
        $student = $this->userWithRole('student');
        $course = $this->paidCourse();

        $this->actingAs($student)->post(route('enrollment.store', $course));

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_a_student_who_has_paid_can_enrol(): void
    {
        $student = $this->userWithRole('student');
        $course = $this->paidCourse();
        $this->markPurchased($student, $course);

        $enrollment = app(EnrollmentService::class)->selfEnroll($student, $course);

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_an_unpaid_order_does_not_open_the_gate(): void
    {
        $student = $this->userWithRole('student');
        $course = $this->paidCourse();

        // Pending, not paid — putting something in a basket is not buying it.
        $order = Order::factory()->create(['user_id' => $student->id]);
        OrderItem::factory()->forCourse($course)->create(['order_id' => $order->id]);

        $this->expectException(EnrollmentException::class);

        app(EnrollmentService::class)->selfEnroll($student, $course);
    }

    public function test_another_students_purchase_does_not_open_the_gate(): void
    {
        $student = $this->userWithRole('student');
        $other = $this->userWithRole('student');
        $course = $this->paidCourse();

        $this->markPurchased($other, $course);

        $this->expectException(EnrollmentException::class);

        app(EnrollmentService::class)->selfEnroll($student, $course);
    }

    public function test_a_free_course_is_unaffected_by_the_paywall(): void
    {
        // Every pre-commerce enrolment path must keep behaving exactly as before.
        $student = $this->userWithRole('student');
        $course = Course::factory()->published()->create();

        $enrollment = app(EnrollmentService::class)->selfEnroll($student, $course);

        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_fulfilling_a_paid_order_enrols_the_buyer(): void
    {
        Notification::fake();

        $student = $this->userWithRole('student');
        $course = $this->paidCourse();

        $order = Order::factory()->create(['user_id' => $student->id, 'total' => 7000]);
        OrderItem::factory()->forCourse($course)->create(['order_id' => $order->id]);

        app(OrderFulfilmentService::class)->markPaid($order, 'test_ref');

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Active->value,
            // Recorded as a purchase for the roster's audit trail.
            'source' => EnrollmentSource::Purchase->value,
        ]);
    }

    public function test_fulfilment_is_idempotent_and_does_not_double_enrol(): void
    {
        // Gateways retry webhooks; buyers refresh callbacks. Running twice must change
        // nothing the second time.
        Notification::fake();

        $student = $this->userWithRole('student');
        $course = $this->paidCourse();

        $order = Order::factory()->create(['user_id' => $student->id, 'total' => 7000]);
        OrderItem::factory()->forCourse($course)->create(['order_id' => $order->id]);

        $fulfilment = app(OrderFulfilmentService::class);

        $this->assertTrue($fulfilment->markPaid($order, 'ref-1'), 'First call transitions the order.');
        $this->assertFalse($fulfilment->markPaid($order->fresh(), 'ref-2'), 'Second call is a no-op.');

        $this->assertSame(1, $course->enrollments()->where('user_id', $student->id)->count());
        // The first reference stands — a replay must not overwrite the real one.
        $this->assertSame('ref-1', $order->fresh()->gateway_reference);
    }

    public function test_a_late_failure_notice_cannot_revoke_a_completed_purchase(): void
    {
        Notification::fake();

        $student = $this->userWithRole('student');
        $order = Order::factory()->paid()->create(['user_id' => $student->id]);

        app(OrderFulfilmentService::class)->markFailed($order);

        $this->assertTrue($order->fresh()->isPaid());
    }
}
