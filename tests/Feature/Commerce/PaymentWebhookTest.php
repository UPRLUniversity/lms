<?php

namespace Tests\Feature\Commerce;

use App\Enums\OrderStatus;
use App\Models\Course;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The webhook endpoint is public and unauthenticated — anyone on the internet can POST
 * to it. Everything therefore rests on the signature check and on the rule that the
 * body is only trusted to say WHICH order to look at, never that it was paid.
 */
class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function paystack(): PaymentMethod
    {
        return PaymentMethod::factory()->paystack()->create([
            'config' => ['public_key' => 'pk_test_x', 'secret_key' => self::SECRET],
        ]);
    }

    private function order(float $total = 7000): Order
    {
        $user = $this->userWithRole('student');
        $course = Course::factory()->published()->create(['is_free' => false]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => $total,
            'subtotal' => $total,
            'payment_method_key' => 'paystack',
        ]);
        OrderItem::factory()->forCourse($course, $total)->create(['order_id' => $order->id]);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Order $order, string $event = 'charge.success'): array
    {
        return [
            'event' => $event,
            'data' => [
                'reference' => $order->reference,
                'status' => 'success',
                'amount' => Money::toMinor($order->total),
                'currency' => 'NGN',
            ],
        ];
    }

    private function sign(array $body): string
    {
        return hash_hmac('sha512', json_encode($body), self::SECRET);
    }

    /**
     * Paystack's verify endpoint, which the app re-reads before granting anything.
     */
    private function fakeVerify(Order $order, string $status = 'success', ?int $amountMinor = null): void
    {
        Http::fake([
            '*/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'reference' => $order->reference,
                    'status' => $status,
                    'amount' => $amountMinor ?? Money::toMinor($order->total),
                    'currency' => 'NGN',
                    'channel' => 'card',
                ],
            ]),
        ]);
    }

    public function test_a_correctly_signed_webhook_settles_the_order_and_grants_access(): void
    {
        $method = $this->paystack();
        $order = $this->order();
        $this->fakeVerify($order);

        $body = $this->body($order);

        $this->call(
            'POST',
            route('payments.webhook', ['method' => 'paystack']),
            [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->sign($body), 'CONTENT_TYPE' => 'application/json'],
            json_encode($body),
        )->assertOk();

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $order->user_id,
            'course_id' => $order->courseItems()->first()->course_id,
        ]);
    }

    public function test_an_unsigned_webhook_is_rejected(): void
    {
        // Without the signature check, anyone could POST "paid" for any reference.
        $this->paystack();
        $order = $this->order();

        $this->postJson(route('payments.webhook', ['method' => 'paystack']), $this->body($order))
            ->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_a_wrongly_signed_webhook_is_rejected(): void
    {
        $this->paystack();
        $order = $this->order();
        $body = $this->body($order);

        $this->call(
            'POST',
            route('payments.webhook', ['method' => 'paystack']),
            [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => hash_hmac('sha512', json_encode($body), 'the-wrong-key')],
            json_encode($body),
        )->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_a_replayed_webhook_does_not_enrol_or_charge_twice(): void
    {
        $method = $this->paystack();
        $order = $this->order();
        $this->fakeVerify($order);

        $body = $this->body($order);
        $headers = ['HTTP_X-PAYSTACK-SIGNATURE' => $this->sign($body), 'CONTENT_TYPE' => 'application/json'];
        $url = route('payments.webhook', ['method' => 'paystack']);

        $this->call('POST', $url, [], [], [], $headers, json_encode($body))->assertOk();
        $this->call('POST', $url, [], [], [], $headers, json_encode($body))->assertOk();

        $courseId = $order->courseItems()->first()->course_id;

        $this->assertSame(1, \App\Models\Enrollment::where('user_id', $order->user_id)
            ->where('course_id', $courseId)->count());
        $this->assertSame(1, Order::where('status', OrderStatus::Paid->value)->count());
    }

    public function test_a_webhook_claiming_payment_is_refused_when_verification_disagrees(): void
    {
        // The body says paid; Paystack's own API says otherwise. The API wins.
        $this->paystack();
        $order = $this->order();
        $this->fakeVerify($order, status: 'failed');

        $body = $this->body($order);

        $this->call(
            'POST',
            route('payments.webhook', ['method' => 'paystack']),
            [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->sign($body), 'CONTENT_TYPE' => 'application/json'],
            json_encode($body),
        )->assertOk();

        $this->assertNotSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_an_amount_mismatch_refuses_to_settle_the_order(): void
    {
        // A 1 naira payment must not settle a 7,000 naira order.
        $this->paystack();
        $order = $this->order(7000);
        $this->fakeVerify($order, amountMinor: 100);

        $body = $this->body($order);

        $this->call(
            'POST',
            route('payments.webhook', ['method' => 'paystack']),
            [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->sign($body), 'CONTENT_TYPE' => 'application/json'],
            json_encode($body),
        )->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_an_unrelated_event_is_acknowledged_but_ignored(): void
    {
        // Acknowledged with 200 so the gateway stops retrying something we do not act on.
        $this->paystack();
        $order = $this->order();
        $body = $this->body($order, 'subscription.create');

        $this->call(
            'POST',
            route('payments.webhook', ['method' => 'paystack']),
            [], [], [],
            ['HTTP_X-PAYSTACK-SIGNATURE' => $this->sign($body), 'CONTENT_TYPE' => 'application/json'],
            json_encode($body),
        )->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    public function test_a_webhook_for_an_unknown_payment_method_is_a_404(): void
    {
        $this->postJson(route('payments.webhook', ['method' => 'not-a-gateway']), [])->assertNotFound();
    }

    public function test_the_webhook_endpoint_is_exempt_from_csrf(): void
    {
        // Gateways post server-to-server and cannot carry a token. A 419 here would mean
        // every real payment silently failed to confirm.
        $this->paystack();

        $response = $this->post(route('payments.webhook', ['method' => 'paystack']), []);

        $this->assertNotSame(419, $response->getStatusCode());
    }

    public function test_the_sandbox_driver_refuses_to_settle_from_an_unauthenticated_post(): void
    {
        // The sandbox has no signature to check, so it must never treat a public POST as
        // proof of payment.
        PaymentMethod::factory()->create();
        $order = Order::factory()->create(['payment_method_key' => 'sandbox']);

        $this->postJson(route('payments.webhook', ['method' => 'sandbox']), [
            'reference' => $order->reference,
        ])->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }
}
