<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Commerce\OrderFulfilmentService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server payment events.
 *
 * This endpoint is public and unauthenticated — anyone on the internet can POST to it.
 * Everything it does therefore rests on the driver's signature check, and on the rule
 * that the body is only ever trusted to say WHICH order to look at. What that order
 * costs, and whether the gateway really settled it, are re-read from our own records
 * and the provider's API.
 *
 * Responses are 200 wherever we understood the event, even when we chose not to act,
 * so the gateway stops retrying. A 403 is reserved for a failed signature: that is a
 * real problem and should keep being reported.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly OrderFulfilmentService $fulfilment,
    ) {}

    public function __invoke(Request $request, string $method): JsonResponse
    {
        // Found by key regardless of is_enabled: an admin switching a method off must
        // not strand payments already in flight.
        $paymentMethod = $this->gateways->findByKey($method);

        if ($paymentMethod === null || ! $paymentMethod->hasDriver()) {
            return response()->json(['ok' => false, 'message' => 'Unknown payment method.'], 404);
        }

        $result = $this->gateways->driver($paymentMethod)->handleWebhook($request, $paymentMethod);

        if ($result->isFailed() && $result->orderReference === null) {
            // A signature failure (or an otherwise unattributable event). Logged
            // without the body, which may carry cardholder data.
            Log::warning('Rejected payment webhook.', [
                'method' => $method,
                'reason' => $result->message,
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => false], 403);
        }

        if ($result->orderReference === null) {
            return response()->json(['ok' => true, 'message' => $result->message ?? 'Ignored.']);
        }

        $order = Order::query()->where('reference', $result->orderReference)->first();

        if ($order === null) {
            // Acknowledged so the gateway stops retrying — a reference we have never
            // issued is not something a retry will fix.
            Log::info('Payment webhook for an unknown order.', ['method' => $method, 'reference' => $result->orderReference]);

            return response()->json(['ok' => true, 'message' => 'Unknown order.']);
        }

        if ($result->isPaid()) {
            // Re-verify against the provider before granting anything. The webhook body
            // said "paid"; this asks the gateway's own API whether that is true and
            // whether the amount matches what we charged.
            $verified = $this->gateways->driver($paymentMethod)->verify($order, $paymentMethod, $request);

            if (! $verified->isPaid()) {
                Log::warning('Webhook claimed payment but verification disagreed.', [
                    'order' => $order->reference,
                    'method' => $method,
                ]);

                return response()->json(['ok' => true, 'message' => 'Not verified.']);
            }

            // markPaid is idempotent: a replayed webhook returns false and changes
            // nothing, rather than double-enrolling or double-redeeming the coupon.
            $this->fulfilment->markPaid($order, $verified->reference, $verified->payload);

            return response()->json(['ok' => true]);
        }

        if ($result->isFailed()) {
            $this->fulfilment->markFailed($order, $result->payload);
        }

        return response()->json(['ok' => true]);
    }
}
