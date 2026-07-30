<?php

namespace Database\Seeders;

use App\Enums\CouponScope;
use App\Enums\CouponType;
use App\Enums\PaymentEnvironment;
use App\Enums\Role;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\PaymentMethod;
use App\Models\Programme;
use App\Models\User;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\OrderFulfilmentService;
use App\Services\Commerce\CartService;
use Illuminate\Database\Seeder;

/**
 * Turns the store on for the demo: prices the NIPR papers, installs the payment
 * methods, creates a spread of discount codes, and places real orders in every state
 * so the admin screens are not empty at demo time.
 *
 * Two deliberate choices about which courses cost money:
 *
 *  - The ~40 NIPR papers (CPR/DPR/NPV) become PAID, inheriting their programme's
 *    per-paper fee. Nothing is written onto the courses for this — is_free is simply
 *    switched off and PricingService resolves the rest.
 *  - The eight hand-written demo courses stay FREE (they sit in the Master Class,
 *    whose fees are zero), so every pre-commerce demo flow — self-enrol, waitlist,
 *    approval queue — still works exactly as it did.
 *
 * Idempotent: safe to re-run without duplicating codes, methods or orders.
 */
class CommerceSeeder extends Seeder
{
    public function run(): void
    {
        $this->priceCourses();
        $this->installPaymentMethods();
        $this->seedCoupons();
        $this->seedOrders();
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */

    /**
     * Charge for any course whose primary programme has a per-paper fee. Courses in a
     * zero-fee programme (the Master Class) and courses in no programme at all stay
     * free, which is the safe default the migration set.
     */
    private function priceCourses(): void
    {
        $paidProgrammes = Programme::query()->where('per_paper_fee', '>', 0)->pluck('id');

        if ($paidProgrammes->isEmpty()) {
            return;
        }

        Course::query()
            ->whereHas('programmeParts', fn ($q) => $q
                ->whereIn('programme_parts.programme_id', $paidProgrammes)
                ->where('course_programme_part.is_primary', true))
            ->update(['is_free' => false]);

        // One deliberate exception so the catalogue shows a genuinely free NIPR paper
        // beside the paid ones — the "free taster" case the pricing rules allow for.
        Course::where('code', 'GNS101')->update(['is_free' => true]);

        // And one override, so the demo proves a course can depart from its
        // programme's tier without the programme changing.
        Course::where('code', 'DPR415')->update(['is_free' => false, 'price_override' => 25000]);
    }

    /*
    |--------------------------------------------------------------------------
    | Payment methods
    |--------------------------------------------------------------------------
    */

    private function installPaymentMethods(): void
    {
        // Sandbox on by default: a fresh install must be able to complete a purchase
        // end-to-end with no merchant account and no keys.
        PaymentMethod::updateOrCreate(
            ['key' => 'sandbox'],
            [
                'label' => 'Sandbox (test only)',
                'is_enabled' => true,
                'environment' => PaymentEnvironment::Test,
                'config' => [],
                'position' => 1,
            ],
        );

        PaymentMethod::updateOrCreate(
            ['key' => 'bank_transfer'],
            [
                'label' => 'Bank transfer',
                'is_enabled' => true,
                'environment' => PaymentEnvironment::Live,
                'config' => [],
                'instructions' => '<p>Transfer the total to:</p>'
                    .'<p><strong>University of Public Relations and Leadership</strong><br>'
                    .'Account number: 0123456789<br>'
                    .'Bank: Demo Bank Nigeria PLC</p>'
                    .'<p>Quote your order reference so we can match your payment. '
                    .'Access opens as soon as we confirm it, usually within one working day.</p>',
                'position' => 2,
            ],
        );

        // Present but off and keyless — the state an institution actually starts in,
        // and what makes the "needs configuration" path demonstrable.
        PaymentMethod::updateOrCreate(
            ['key' => 'paystack'],
            [
                'label' => 'Paystack',
                'is_enabled' => false,
                'environment' => PaymentEnvironment::Test,
                'config' => ['public_key' => '', 'secret_key' => ''],
                'position' => 3,
            ],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Coupons
    |--------------------------------------------------------------------------
    */

    private function seedCoupons(): void
    {
        $admin = User::role(Role::Admin->value)->first() ?? User::role(Role::SuperAdmin->value)->first();
        $cpr = Programme::where('code', 'CPR')->first();
        $course = Course::where('code', 'CPR112')->first();

        $rows = [
            [
                'code' => 'WELCOME20',
                'name' => 'Open day promotion',
                'type' => CouponType::Percentage,
                'value' => 20,
                'scope' => CouponScope::Global,
                'expires_at' => now()->addMonths(3),
            ],
            [
                'code' => 'NIPR5000',
                'name' => 'Institute member rebate',
                'type' => CouponType::Fixed,
                'value' => 5000,
                'scope' => CouponScope::Global,
                'max_redemptions' => 100,
            ],
            [
                'code' => 'CPRSTART',
                'name' => 'Certificate programme launch',
                'type' => CouponType::Percentage,
                'value' => 15,
                'scope' => CouponScope::Programme,
                'programme_id' => $cpr?->id,
            ],
            [
                'code' => 'SCHOLAR',
                'name' => 'Scholarship place',
                'type' => CouponType::Full,
                'value' => 0,
                'scope' => CouponScope::Course,
                'course_id' => $course?->id,
                'max_redemptions' => 5,
            ],
            [
                'code' => 'LASTYEAR',
                'name' => 'Expired — for testing the rejection message',
                'type' => CouponType::Percentage,
                'value' => 50,
                'scope' => CouponScope::Global,
                'expires_at' => now()->subMonth(),
            ],
        ];

        foreach ($rows as $row) {
            // Skip rows whose target did not seed (a partial database).
            if (($row['scope'] === CouponScope::Programme && ! $cpr)
                || ($row['scope'] === CouponScope::Course && ! $course)) {
                continue;
            }

            Coupon::updateOrCreate(['code' => $row['code']], $row + [
                'per_user_limit' => 1,
                'is_active' => true,
                'created_by' => $admin?->id,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    /**
     * Three real orders, each placed through CheckoutService rather than inserted by
     * hand — so the totals, the entry fees and the enrolments are exactly what the
     * live code produces, and the demo cannot drift from the implementation.
     */
    private function seedOrders(): void
    {
        $students = User::role(Role::Student->value)->orderBy('id')->take(3)->get();

        if ($students->count() < 3) {
            return;
        }

        $carts = app(CartService::class);
        $checkout = app(CheckoutService::class);
        $fulfilment = app(OrderFulfilmentService::class);

        $paidCourses = Course::query()
            ->where('is_free', false)
            ->whereHas('programmeParts')
            ->orderBy('code')
            ->take(4)
            ->get();

        if ($paidCourses->count() < 3) {
            return;
        }

        // 1. A completed purchase: two CPR papers plus the one-off entry fees.
        $this->placeOrder($students[0], [$paidCourses[0], $paidCourses[1]], 'sandbox', $carts, $checkout, function ($order) use ($fulfilment) {
            $fulfilment->markPaid($order, 'sandbox_demo_paid');
        });

        // 2. A bank transfer waiting on an admin to confirm it — the "Mark paid" demo.
        $this->placeOrder($students[1], [$paidCourses[2]], 'bank_transfer', $carts, $checkout, function ($order) use ($fulfilment) {
            $fulfilment->markAwaitingPayment($order);
        });

        // 3. A discounted purchase, so a redemption exists against WELCOME20.
        $this->placeOrder($students[2], [$paidCourses[3] ?? $paidCourses[0]], 'sandbox', $carts, $checkout, function ($order) use ($fulfilment) {
            $fulfilment->markPaid($order, 'sandbox_demo_discounted');
        }, 'WELCOME20');
    }

    /**
     * @param  array<int, Course>  $courses
     */
    private function placeOrder(
        User $student,
        array $courses,
        string $method,
        CartService $carts,
        CheckoutService $checkout,
        callable $settle,
        ?string $coupon = null,
    ): void {
        // Idempotency: if this student already has an order, leave them alone.
        if ($student->orders()->exists()) {
            return;
        }

        $cart = $carts->current($student);
        $carts->clear($cart);

        foreach ($courses as $course) {
            $carts->add($cart, $course);
        }

        $cart->refresh();

        try {
            $order = $checkout->place($cart, $student, $method, ['name' => $student->name], $coupon);
            $settle($order);
        } catch (\Throwable $e) {
            // A demo seeder must never break `migrate:fresh --seed`. Report and move on.
            $this->command?->warn("CommerceSeeder: could not place a demo order — {$e->getMessage()}");
        }

        $carts->clear($carts->current($student));
    }
}
