<?php

namespace Tests\Feature\Commerce;

use App\Exceptions\CouponException;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\User;
use App\Services\Commerce\CartService;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\CouponService;
use App\Services\Commerce\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $coupons;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coupons = app(CouponService::class);
    }

    private function paidCourse(float $perPaper = 10000): Course
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

    private function linesFor(User $user, Course ...$courses)
    {
        return app(PricingService::class)->linesFor($this->cartFor($user, ...$courses), $user);
    }

    public function test_a_percentage_code_discounts_the_course_lines(): void
    {
        $user = User::factory()->create();
        $lines = $this->linesFor($user, $this->paidCourse(10000));
        $coupon = Coupon::factory()->percentage(20)->create();

        $this->assertSame(2000.0, $this->coupons->discountFor($coupon, $lines));
    }

    public function test_a_code_never_discounts_the_programme_entry_fees(): void
    {
        // Registration and administration are the Institute's charge for entering a
        // programme, not a course price. A 100% code must not wipe them out.
        $user = User::factory()->create();
        $lines = $this->linesFor($user, $this->paidCourse(10000));
        $coupon = Coupon::factory()->full()->create();

        // The cart is 10,000 + 20,000 + 25,000 = 55,000, but only 10,000 is eligible.
        $this->assertSame(55000.0, round($lines->sum(fn ($l) => $l->amount), 2));
        $this->assertSame(10000.0, $this->coupons->discountFor($coupon, $lines));
    }

    public function test_a_fixed_code_never_discounts_more_than_the_eligible_amount(): void
    {
        // A 50,000 coupon against a 10,000 course discounts 10,000 — the institution
        // must never end up owing the student money.
        $user = User::factory()->create();
        $lines = $this->linesFor($user, $this->paidCourse(10000));
        $coupon = Coupon::factory()->fixed(50000)->create();

        $this->assertSame(10000.0, $this->coupons->discountFor($coupon, $lines));
    }

    public function test_a_course_scoped_code_only_touches_its_own_course(): void
    {
        $user = User::factory()->create();
        $mine = $this->paidCourse(10000);
        $other = $this->paidCourse(10000);

        $lines = $this->linesFor($user, $mine, $other);
        $coupon = Coupon::factory()->percentage(50)->forCourse($mine)->create();

        $this->assertSame(5000.0, $this->coupons->discountFor($coupon, $lines));
    }

    public function test_a_programme_scoped_code_covers_every_course_in_that_programme(): void
    {
        $user = User::factory()->create();
        $first = $this->paidCourse(10000);
        $programme = $first->primaryProgramme();

        $second = Course::factory()->published()->create(['is_free' => false]);
        $second->programmeParts()->attach(
            ProgrammePart::factory()->for($programme)->create()->id,
            ['is_primary' => true],
        );

        $lines = $this->linesFor($user, $first, $second->load('programmeParts.programme'));
        $coupon = Coupon::factory()->percentage(10)->forProgramme($programme)->create();

        $this->assertSame(2000.0, $this->coupons->discountFor($coupon, $lines));
    }

    public function test_codes_are_matched_case_insensitively(): void
    {
        Coupon::factory()->create(['code' => 'SAVE20']);

        $this->assertSame('SAVE20', $this->coupons->find('  save20 ')->code);
    }

    public function test_an_unknown_and_an_inactive_code_answer_identically(): void
    {
        // Probing must not confirm that a code exists.
        Coupon::factory()->inactive()->create(['code' => 'HIDDEN']);

        foreach (['NOSUCHCODE', 'HIDDEN'] as $code) {
            try {
                $this->coupons->find($code);
                $this->fail("Expected {$code} to be rejected.");
            } catch (CouponException $e) {
                $this->assertSame('That code is not valid.', $e->getMessage());
            }
        }
    }

    public function test_an_expired_code_is_rejected(): void
    {
        Coupon::factory()->expired()->create(['code' => 'LASTYEAR']);

        $this->expectExceptionMessage('That code has expired.');
        $this->coupons->find('LASTYEAR');
    }

    public function test_a_scheduled_code_is_rejected_before_it_starts(): void
    {
        Coupon::factory()->scheduled()->create(['code' => 'SOON']);

        $this->expectExceptionMessage('That code is not active yet.');
        $this->coupons->find('SOON');
    }

    public function test_a_code_at_its_global_limit_is_rejected(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'ONLYONE', 'max_redemptions' => 1]);
        $coupon->redemptions()->create([
            'user_id' => User::factory()->create()->id,
            'order_id' => Order::factory()->paid()->create()->id,
            'discount_amount' => 100,
        ]);

        $this->expectExceptionMessage('That code has reached its usage limit.');
        $this->coupons->find('ONLYONE');
    }

    public function test_a_student_cannot_use_the_same_code_twice(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'ONCEONLY', 'per_user_limit' => 1]);
        $coupon->redemptions()->create([
            'user_id' => $user->id,
            'order_id' => Order::factory()->paid()->create(['user_id' => $user->id])->id,
            'discount_amount' => 100,
        ]);

        $this->expectExceptionMessage('You have already used that code.');
        $this->coupons->find('ONCEONLY', $user);
    }

    public function test_a_code_that_matches_nothing_in_the_cart_is_rejected(): void
    {
        $user = User::factory()->create();
        $lines = $this->linesFor($user, $this->paidCourse());
        $coupon = Coupon::factory()->percentage(20)->forCourse(Course::factory()->published()->create())->create();

        $this->expectExceptionMessage('That code does not apply to anything in your cart.');
        $this->coupons->validate($coupon->code, $lines, $user);
    }

    public function test_applying_a_bad_code_on_the_cart_shows_the_reason_and_still_prices_the_cart(): void
    {
        // A mistyped code must not blank the page.
        $user = User::factory()->create();
        $cart = $this->cartFor($user, $this->paidCourse());

        $totals = app(CheckoutService::class)->quote($cart, $user, 'NONSENSE');

        $this->assertSame('That code is not valid.', $totals->couponError);
        $this->assertSame(55000.0, $totals->total);
    }

    public function test_a_student_can_apply_and_remove_a_code_on_the_cart_page(): void
    {
        $user = User::factory()->create();
        Coupon::factory()->percentage(20)->create(['code' => 'SAVE20']);
        $this->cartFor($user, $this->paidCourse(10000));

        $this->actingAs($user)
            ->from(route('cart.index'))
            ->post(route('cart.coupon.apply'), ['code' => 'save20'])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHas('cart.coupon', 'SAVE20');

        $this->actingAs($user)
            ->withSession(['cart.coupon' => 'SAVE20'])
            ->delete(route('cart.coupon.remove'))
            ->assertSessionMissing('cart.coupon');
    }

    public function test_applying_a_rejected_code_reports_the_error_and_stores_nothing(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, $this->paidCourse());

        $this->actingAs($user)
            ->from(route('cart.index'))
            ->post(route('cart.coupon.apply'), ['code' => 'NOPE'])
            ->assertSessionHas('error')
            ->assertSessionMissing('cart.coupon');
    }

    public function test_redeeming_the_same_order_twice_records_one_redemption(): void
    {
        // A replayed webhook must not burn a second use of the code.
        $user = User::factory()->create();
        $coupon = Coupon::factory()->percentage(20)->create();
        $order = Order::factory()->paid()->create([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'discount_total' => 2000,
        ]);

        $this->coupons->redeem($order);
        $this->coupons->redeem($order);

        $this->assertSame(1, $coupon->fresh()->redemptionCount());
    }
}
