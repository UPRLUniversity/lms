<?php

namespace Tests\Feature\Hardening;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Programme;
use App\Models\ProgrammePart;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Commerce\CartService;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\OrderFulfilmentService;
use App\Support\Security\RouteGuardAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Section 15 hardening sweep, as executable assertions.
 *
 * Two of this section's acceptance criteria are regression guarantees rather than new
 * features — the route-permission map must stay empty of unguarded mutating routes, and
 * the Section 12 money invariants must be RE-CONFIRMED after Sections 13 and 14 touched
 * checkout and the curriculum. "Re-confirmed" is the operative word: reading the code and
 * concluding it still looks right is not confirmation, so each invariant is exercised
 * here against a real attempt to violate it.
 */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        // The sandbox gateway, enabled — without an offered method checkout redirects
        // back with an error and writes no order, which would make the money-invariant
        // assertions below pass for entirely the wrong reason.
        PaymentMethod::factory()->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Route-permission map (acceptance criterion 4)
    |--------------------------------------------------------------------------
    */

    public function test_no_mutating_route_is_unguarded_anywhere_in_the_app(): void
    {
        $unguarded = app(RouteGuardAudit::class)->unguardedMutating();

        $this->assertSame(
            [],
            $unguarded->map(fn (array $r) => "{$r['method']} /{$r['uri']} → {$r['action']}")->all(),
            'Every mutating route must be guarded by middleware, a policy/authorize call, '
            .'or carry an explicit justification in RouteGuardAudit::PUBLIC_BY_DESIGN.',
        );
    }

    public function test_the_map_actually_covers_the_whole_app_including_commerce_and_admin(): void
    {
        $map = app(RouteGuardAudit::class)->map();

        // A map that silently covered nothing would also report zero unguarded routes,
        // so assert it really is looking at the areas the criterion names.
        $this->assertGreaterThan(100, $map->where('mutating', true)->count());

        foreach (['checkout', 'webhooks/payments', 'admin/programmes', 'admin/coupons', 'admin/payment-methods'] as $area) {
            $this->assertTrue(
                $map->contains(fn (array $r) => str_contains($r['uri'], $area)),
                "The route map must cover {$area}.",
            );
        }
    }

    public function test_admin_and_commerce_mutating_routes_are_all_guarded(): void
    {
        $map = app(RouteGuardAudit::class)->map();

        $sensitive = $map->filter(fn (array $r) => $r['mutating'] && (
            str_starts_with($r['uri'], 'admin/') || str_contains($r['uri'], 'checkout')
        ));

        $this->assertTrue($sensitive->isNotEmpty());

        foreach ($sensitive as $route) {
            $this->assertTrue($route['guarded'], "Unguarded: {$route['method']} /{$route['uri']}");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Section 12 money invariants — re-confirmed (acceptance criterion 5)
    |--------------------------------------------------------------------------
    */

    public function test_money_is_re_resolved_server_side_and_a_posted_total_is_ignored(): void
    {
        $user = $this->buyer();
        $course = $this->paidCourse(7000);
        $cart = $this->cartFor($user, $course);

        // The true price, resolved from the programme's fees: 7,000 per paper +
        // 20,000 registration + 25,000 administration. Captured BEFORE checkout, which
        // empties the cart.
        $expected = app(CheckoutService::class)
            ->quote($cart->load('items.course.programmeParts.programme'), $user)
            ->total;

        $this->assertEqualsWithDelta(52000.0, $expected, 0.01);

        // Tamper with the stored cart line the way a compromised client would, then
        // post a total of our choosing. Neither may influence what is charged.
        $cart->items()->update(['unit_price' => 1.00]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'payment_method' => 'sandbox',
            'terms' => '1',
            'total' => 1.00,
            'subtotal' => 1.00,
            'discount_total' => 99999,
            'amount' => 1.00,
        ])->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();

        $this->assertSame(0.0, (float) $order->discount_total, 'A posted discount must be ignored entirely.');
        $this->assertGreaterThan(1.00, (float) $order->total, 'A tampered cart price must not become the charge.');
        $this->assertEqualsWithDelta($expected, (float) $order->total, 0.01);
    }

    public function test_an_unsigned_webhook_is_rejected(): void
    {
        $method = PaymentMethod::factory()->create([
            'key' => 'paystack',
            'label' => 'Paystack',
            'is_enabled' => true,
            'config' => ['public_key' => 'pk_test', 'secret_key' => 'sk_test_secret'],
        ]);

        $order = $this->pendingOrder();

        // A forged body claiming the order is paid, with no valid signature.
        $response = $this->postJson(route('payments.webhook', 'paystack'), [
            'event' => 'charge.success',
            'data' => ['reference' => $order->reference, 'status' => 'success'],
        ]);

        $response->assertForbidden();
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status, 'An unsigned webhook must never mark an order paid.');
    }

    public function test_fulfilment_is_idempotent(): void
    {
        $user = $this->buyer();
        $course = $this->paidCourse();
        $order = $this->pendingOrder($user, $course);

        $fulfilment = app(OrderFulfilmentService::class);

        $first = $fulfilment->markPaid($order, 'ref-1');
        $second = $fulfilment->markPaid($order->fresh(), 'ref-1');
        $third = $fulfilment->markPaid($order->fresh(), 'ref-1');

        $this->assertTrue($first, 'The first call must transition the order.');
        $this->assertFalse($second, 'A replayed webhook must change nothing.');
        $this->assertFalse($third);

        // The thing idempotency is actually protecting: one enrolment, not three.
        $this->assertSame(
            1,
            $user->enrollments()->where('course_id', $course->id)->count(),
            'Replaying fulfilment must not enrol the buyer repeatedly.',
        );
        $this->assertSame(1, Order::where('id', $order->id)->where('status', OrderStatus::Paid)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Transport & headers
    |--------------------------------------------------------------------------
    */

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }

    public function test_hsts_is_not_sent_over_plain_http(): void
    {
        // Pinning a developer's browser to HTTPS on localhost is painful to undo, and
        // the header means nothing on a plaintext response anyway.
        $this->get(route('home'))->assertHeaderMissing('Strict-Transport-Security');
    }

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    */

    public function test_sensitive_public_endpoints_are_throttled(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $expectations = [
            'payments.webhook' => 'the gateway callback',
            'verify.lookup' => 'the certificate verification portal',
            'checkout.store' => 'placing an order',
            'password.email' => 'password-reset requests',
            'invitations.accept.store' => 'invitation acceptance (token brute-force)',
        ];

        foreach ($expectations as $name => $why) {
            $route = $routes->first(fn ($r) => $r->getName() === $name);

            $this->assertNotNull($route, "Route {$name} is missing.");

            $throttled = collect(app('router')->gatherRouteMiddleware($route))
                ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle'));

            $this->assertTrue($throttled, "Expected a rate limit on {$name} — {$why}.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Mass assignment
    |--------------------------------------------------------------------------
    */

    public function test_models_added_since_v2_declare_an_explicit_fillable_allow_list(): void
    {
        $models = [
            Programme::class,
            ProgrammePart::class,
            Cart::class,
            CartItem::class,
            Order::class,
            OrderItem::class,
            Coupon::class,
            PaymentMethod::class,
            Setting::class,
        ];

        foreach ($models as $class) {
            $model = new $class;

            $this->assertNotEmpty(
                $model->getFillable(),
                "{$class} must declare \$fillable — an empty allow-list with a non-empty \$guarded is how mass-assignment holes appear.",
            );
        }
    }

    public function test_an_order_cannot_be_mass_assigned_to_paid_from_request_input(): void
    {
        $user = $this->buyer();
        $course = $this->paidCourse();
        $cart = $this->cartFor($user, $course);

        $this->actingAs($user)->post(route('checkout.store'), [
            'payment_method' => 'sandbox',
            'terms' => '1',
            // The attack: try to have the order written as already paid.
            'status' => OrderStatus::Paid->value,
            'paid_at' => now()->toDateTimeString(),
        ])->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();

        // The sandbox driver settles instantly, so assert on the mechanism rather than
        // the end state: whatever the status, it must have come from fulfilment, which
        // is the only thing that grants access — never from posted input.
        if ($order->status === OrderStatus::Paid) {
            $this->assertNotNull($order->paid_at);
            $this->assertNotNull($order->gateway_reference, 'A paid order must carry a gateway reference from real fulfilment.');
        } else {
            $this->assertSame(OrderStatus::Pending, $order->status);
            $this->assertNull($order->paid_at);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration hygiene
    |--------------------------------------------------------------------------
    */

    public function test_env_example_documents_required_keys_without_leaking_secrets(): void
    {
        $path = base_path('.env.example');
        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        foreach (['APP_KEY', 'DB_CONNECTION', 'MAIL_MAILER', 'CLOUDINARY_URL', 'MEDIA_DRIVER'] as $key) {
            $this->assertStringContainsString($key, $contents, "{$key} should be documented in .env.example.");
        }

        // A committed example file must never carry a real credential.
        $this->assertDoesNotMatchRegularExpression('/^APP_KEY=base64:.+$/m', $contents);
        $this->assertStringNotContainsString('sk_live_', $contents);
        $this->assertStringNotContainsString('pk_live_', $contents);
    }

    public function test_a_production_env_template_ships_with_safe_defaults(): void
    {
        // .env.example stays developer-friendly (APP_DEBUG=true) because it is the LOCAL
        // template. Production gets its own, and the guard below is what makes mixing
        // them up harmless.
        $path = base_path('.env.production.example');
        $this->assertFileExists($path, 'Production deployment needs its own env template.');

        $contents = file_get_contents($path);

        $this->assertMatchesRegularExpression('/^APP_ENV=production$/m', $contents);
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $contents);
        $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true$/m', $contents);
        $this->assertMatchesRegularExpression('/^QUEUE_CONNECTION=database$/m', $contents);

        // The production template must never carry a real key or credential.
        $this->assertMatchesRegularExpression('/^APP_KEY=\s*$/m', $contents);
        $this->assertStringNotContainsString('sk_live_', $contents);
    }

    public function test_the_app_refuses_to_boot_in_production_with_debug_on(): void
    {
        // The highest-severity misconfiguration this stack has: debug pages print the
        // environment, database password and APP_KEY included. Remembering to set it is
        // not a control; failing to boot is.
        $provider = new AppServiceProvider($this->app);

        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_DEBUG is enabled while APP_ENV=production/');

        $provider->boot();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function buyer(): User
    {
        return $this->userWithRole(Role::Student->value);
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

    private function pendingOrder(?User $user = null, ?Course $course = null): Order
    {
        $user ??= $this->buyer();
        $course ??= $this->paidCourse();

        $cart = $this->cartFor($user, $course);

        return app(CheckoutService::class)->place($cart, $user, 'sandbox');
    }
}
