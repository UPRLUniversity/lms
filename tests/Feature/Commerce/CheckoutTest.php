<?php

namespace Tests\Feature\Commerce;

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use App\Services\Commerce\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        PaymentMethod::factory()->create();          // sandbox, enabled
    }

    private function paidCourse(float $perPaper = 7000): Course
    {
        $programme = Programme::factory()->create([
            'per_paper_fee' => $perPaper,
            'registration_fee' => 20000,
            'administration_fee' => 25000,
        ]);

        $course = Course::factory()->published()->create(['is_free' => false]);
        $course->programmeParts()->attach(
            ProgrammePart::factory()->for($programme)->create()->id,
            ['is_primary' => true],
        );

        return $course->load('programmeParts.programme');
    }

    private function cartFor(User $user, Course ...$courses): Cart
    {
        $carts = app(CartService::class);
        $cart = $carts->current($user);

        foreach ($courses as $course) {
            $carts->add($cart, $course);
        }

        return $cart->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['payment_method' => 'sandbox', 'terms' => '1'], $overrides);
    }

    public function test_a_purchase_completes_and_grants_access(): void
    {
        $user = $this->userWithRole('student');
        $course = $this->paidCourse();
        $this->cartFor($user, $course);

        $this->actingAs($user)->post(route('checkout.store'), $this->payload())->assertRedirect();

        $order = Order::first();

        $this->assertSame(OrderStatus::Paid, $order->status);
        // 7,000 paper + 20,000 registration + 25,000 administration
        $this->assertSame('52000.00', $order->total);
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $course->id]);
    }

    public function test_prices_are_re_resolved_and_a_tampered_post_is_ignored(): void
    {
        // The core security rule: money never comes from the request. Posting a total,
        // a subtotal or a line price must change nothing.
        $user = $this->userWithRole('student');
        $this->cartFor($user, $this->paidCourse());

        $this->actingAs($user)->post(route('checkout.store'), $this->payload([
            'total' => 1,
            'subtotal' => 1,
            'discount_total' => 51999,
            'price' => 1,
        ]))->assertRedirect();

        $this->assertSame('52000.00', Order::first()->total);
    }

    public function test_a_stale_cart_price_does_not_survive_into_the_order(): void
    {
        // The cart snapshots a price for display. If the course is repriced before
        // checkout, the ORDER must use the new price, not the snapshot.
        $user = $this->userWithRole('student');
        $course = $this->paidCourse(7000);
        $cart = $this->cartFor($user, $course);

        $this->assertSame('7000.00', $cart->items->first()->unit_price);

        $course->update(['price_override' => 9000]);

        $this->actingAs($user)->post(route('checkout.store'), $this->payload());

        $order = Order::first();
        $this->assertSame('9000.00', $order->courseItems()->first()->unit_price);
        $this->assertSame('54000.00', $order->total);
    }

    public function test_order_items_snapshot_the_title_so_a_later_rename_cannot_rewrite_history(): void
    {
        $user = $this->userWithRole('student');
        $course = $this->paidCourse();
        $course->update(['title' => 'Original Title']);
        $this->cartFor($user, $course->fresh()->load('programmeParts.programme'));

        $this->actingAs($user)->post(route('checkout.store'), $this->payload());

        $course->update(['title' => 'Renamed Later']);

        $this->assertSame('Original Title', Order::first()->courseItems()->first()->title);
    }

    public function test_checkout_requires_agreeing_to_the_terms(): void
    {
        $user = $this->userWithRole('student');
        $this->cartFor($user, $this->paidCourse());

        $this->actingAs($user)
            ->post(route('checkout.store'), ['payment_method' => 'sandbox'])
            ->assertSessionHasErrors('terms');

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_a_payment_method_that_is_not_enabled(): void
    {
        $user = $this->userWithRole('student');
        $this->cartFor($user, $this->paidCourse());
        PaymentMethod::factory()->paystack()->disabled()->create();

        $this->actingAs($user)
            ->post(route('checkout.store'), $this->payload(['payment_method' => 'paystack']))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    public function test_an_empty_cart_cannot_be_checked_out(): void
    {
        $user = $this->userWithRole('student');

        $this->actingAs($user)->get(route('checkout.show'))->assertRedirect(route('cart.index'));
        $this->actingAs($user)->post(route('checkout.store'), $this->payload())
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_a_guest_cannot_place_an_order(): void
    {
        // Section 13 opened the checkout SCREEN to guests (they see their order and an
        // inline sign-in panel — Public\GuestJourneyTest covers that). Writing an order
        // still requires an account, because an order has to belong to somebody.
        $this->post(route('checkout.store'), $this->payload())->assertRedirect(route('login'));

        $this->assertSame(0, Order::count());
    }

    public function test_a_fully_discounted_order_still_becomes_a_real_paid_order(): void
    {
        // A free total must still produce a receipt and an enrolment — it just skips
        // the gateway.
        $user = $this->userWithRole('student');
        $course = $this->paidCourse();
        Coupon::factory()->full()->create(['code' => 'SCHOLAR']);
        $this->cartFor($user, $course);

        $this->actingAs($user)
            ->withSession(['cart.coupon' => 'SCHOLAR'])
            ->post(route('checkout.store'), $this->payload());

        $order = Order::first();

        // The entry fees are never discounted, so the total is not zero here — but the
        // course itself is free.
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('7000.00', $order->discount_total);
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $course->id]);
    }

    public function test_the_applied_coupon_is_cleared_after_a_purchase(): void
    {
        // Otherwise the next basket silently inherits the discount.
        $user = $this->userWithRole('student');
        Coupon::factory()->percentage(20)->create(['code' => 'SAVE20']);
        $this->cartFor($user, $this->paidCourse());

        $this->actingAs($user)
            ->withSession(['cart.coupon' => 'SAVE20'])
            ->post(route('checkout.store'), $this->payload())
            ->assertSessionMissing('cart.coupon');
    }

    public function test_a_coupon_is_only_redeemed_once_the_order_is_paid(): void
    {
        $user = $this->userWithRole('student');
        $coupon = Coupon::factory()->percentage(20)->create(['code' => 'SAVE20']);
        $this->cartFor($user, $this->paidCourse());

        $this->actingAs($user)
            ->withSession(['cart.coupon' => 'SAVE20'])
            ->post(route('checkout.store'), $this->payload());

        $this->assertSame(1, $coupon->fresh()->redemptionCount());
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_bank_transfer_order_waits_for_a_human(): void
    {
        $user = $this->userWithRole('student');
        $course = $this->paidCourse();
        PaymentMethod::factory()->bankTransfer()->create();
        $this->cartFor($user, $course);

        $this->actingAs($user)->post(route('checkout.store'), $this->payload(['payment_method' => 'bank_transfer']));

        $order = Order::first();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        // Crucially: no access until someone confirms the money arrived.
        $this->assertDatabaseMissing('enrollments', ['user_id' => $user->id, 'course_id' => $course->id]);
    }

    public function test_an_admin_confirming_a_transfer_grants_access(): void
    {
        $user = $this->userWithRole('student');
        $admin = $this->userWithRole('admin');
        $course = $this->paidCourse();
        PaymentMethod::factory()->bankTransfer()->create();
        $this->cartFor($user, $course);

        $this->actingAs($user)->post(route('checkout.store'), $this->payload(['payment_method' => 'bank_transfer']));
        $order = Order::first();

        $this->actingAs($admin)->post(route('admin.orders.mark-paid', $order))->assertRedirect();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertDatabaseHas('enrollments', ['user_id' => $user->id, 'course_id' => $course->id]);
    }

    public function test_a_student_cannot_confirm_their_own_payment(): void
    {
        $user = $this->userWithRole('student');
        $order = Order::factory()->awaitingPayment()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('admin.orders.mark-paid', $order))->assertForbidden();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
    }

    public function test_a_buyer_can_see_their_own_receipt_but_not_someone_elses(): void
    {
        $user = $this->userWithRole('student');
        $other = $this->userWithRole('student');
        $order = Order::factory()->paid()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('orders.show', $order))->assertOk();
        $this->actingAs($other)->get(route('orders.show', $order))->assertForbidden();
    }
}
