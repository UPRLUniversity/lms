<?php

namespace App\Services\Payments\Drivers;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Payments\PaymentGateway;
use App\Support\Money;
use App\Support\Payments\PaymentIntent;
use App\Support\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Paystack — the Nigerian gateway the institution actually uses.
 *
 * Implemented against the HTTP API with the Http facade rather than adding a package:
 * three endpoints are involved, the package landscape for Laravel 12 is unsettled, and
 * a dependency that wraps three calls is a liability rather than a saving.
 *
 * Two things here are security-critical and deliberately not shortcuts:
 *
 *  1. **Amounts are always re-read from our own order**, never from the request. The
 *     callback and webhook tell us WHICH order to look at; what it costs is ours to
 *     know. A gateway response claiming a ₦1 payment cannot settle a ₦59,000 order.
 *
 *  2. **Webhooks are signature-verified** with HMAC-SHA512 over the raw body using the
 *     secret key, compared with hash_equals. The endpoint is public and unauthenticated;
 *     without this check anyone could POST "paid" for any order reference.
 */
class PaystackGateway implements PaymentGateway
{
    public function initiate(Order $order, PaymentMethod $method): PaymentIntent
    {
        $response = Http::withToken($this->secret($method))
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($this->url('/transaction/initialize'), [
                'email' => $order->user->email,
                // Paystack transacts in kobo. Money::toMinor rounds via a safe path —
                // (int) (7000.10 * 100) is 700009 on some platforms.
                'amount' => Money::toMinor($order->total),
                'currency' => $order->currency,
                'reference' => $order->reference,
                'callback_url' => route('checkout.callback', $order),
                'metadata' => [
                    'order_reference' => $order->reference,
                    'user_id' => $order->user_id,
                ],
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack initialise failed.', [
                'order' => $order->reference,
                'status' => $response->status(),
                'body' => $response->json('message'),
            ]);

            return PaymentIntent::instruct('We could not reach Paystack. Please try again.');
        }

        return PaymentIntent::redirect(
            url: (string) $response->json('data.authorization_url'),
            reference: (string) $response->json('data.reference'),
        );
    }

    public function verify(Order $order, PaymentMethod $method, Request $request): PaymentResult
    {
        // The reference we verify is OURS, not whatever the query string offers.
        $response = Http::withToken($this->secret($method))
            ->acceptJson()
            ->timeout(20)
            ->get($this->url('/transaction/verify/'.urlencode($order->reference)));

        if (! $response->successful() || ! $response->json('status')) {
            return PaymentResult::pending($order->reference, ['verify_status' => $response->status()]);
        }

        $data = (array) $response->json('data', []);
        $state = (string) ($data['status'] ?? '');

        if ($state === 'success' && $this->amountMatches($order, $data)) {
            return PaymentResult::paid(
                reference: (string) ($data['reference'] ?? $order->reference),
                orderReference: $order->reference,
                payload: $this->summarise($data),
            );
        }

        if ($state === 'failed' || $state === 'abandoned') {
            return PaymentResult::failed('Paystack reported the payment as '.$state.'.', $order->reference, $this->summarise($data));
        }

        return PaymentResult::pending($order->reference, $this->summarise($data));
    }

    public function handleWebhook(Request $request, PaymentMethod $method): PaymentResult
    {
        if (! $this->signatureIsValid($request, $method)) {
            // Deliberately vague: an attacker probing the endpoint learns nothing.
            return PaymentResult::failed('Invalid signature.');
        }

        $event = (string) $request->input('event');
        $data = (array) $request->input('data', []);
        $reference = $data['reference'] ?? null;

        if ($event !== 'charge.success' || blank($reference)) {
            return PaymentResult::ignored("Unhandled Paystack event: {$event}");
        }

        // Deliberately does NOT trust data.amount or data.status. The caller looks the
        // order up by reference and this driver's verify() re-reads the truth from
        // Paystack's own API before anything is granted.
        return PaymentResult::paid(
            reference: (string) $reference,
            orderReference: (string) $reference,
            payload: $this->summarise($data),
        );
    }

    /**
     * HMAC-SHA512 of the raw request body, keyed with the secret key.
     */
    public function signatureIsValid(Request $request, PaymentMethod $method): bool
    {
        $signature = (string) $request->header('x-paystack-signature', '');
        $secret = $this->secret($method);

        if ($signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);

        // Timing-safe: a plain === leaks the correct prefix length.
        return hash_equals($expected, $signature);
    }

    /**
     * Guard against a settled amount that does not match what we charged — a mismatch
     * means either a misconfiguration or tampering, and must not settle the order.
     *
     * @param  array<string, mixed>  $data
     */
    private function amountMatches(Order $order, array $data): bool
    {
        $paid = (int) ($data['amount'] ?? 0);
        $expected = Money::toMinor($order->total);

        if ($paid === $expected) {
            return true;
        }

        Log::warning('Paystack amount mismatch; refusing to settle.', [
            'order' => $order->reference,
            'expected_minor' => $expected,
            'paid_minor' => $paid,
        ]);

        return false;
    }

    /**
     * Keep only the fields worth retaining. The full payload carries card details and
     * customer records we have no business storing.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function summarise(array $data): array
    {
        return [
            'driver' => 'paystack',
            'reference' => $data['reference'] ?? null,
            'status' => $data['status'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'channel' => $data['channel'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
        ];
    }

    private function secret(PaymentMethod $method): string
    {
        return (string) $method->setting('secret_key', '');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('commerce.paystack.base_url'), '/').$path;
    }
}
