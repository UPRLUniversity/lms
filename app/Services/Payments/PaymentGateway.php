<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Support\Payments\PaymentIntent;
use App\Support\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * A payment provider the app can collect money through.
 *
 * Drivers are resolved by PaymentGatewayManager from the enabled payment_methods row,
 * and they are the ONLY place a provider's SDK or HTTP calls may appear — controllers
 * and models never talk to a gateway directly, the same rule the media services
 * follow for storage.
 *
 * A driver's job stops at "did this payment succeed". Granting access is
 * OrderFulfilmentService's, so no driver ever writes an enrolment.
 */
interface PaymentGateway
{
    /**
     * Begin collecting payment. Returns what the app should do next: redirect the
     * buyer, show them instructions, or treat the order as already settled.
     */
    public function initiate(Order $order, PaymentMethod $method): PaymentIntent;

    /**
     * Confirm a payment on the buyer's return from the gateway. Must be safe to call
     * repeatedly — buyers refresh callback URLs.
     */
    public function verify(Order $order, PaymentMethod $method, Request $request): PaymentResult;

    /**
     * Handle a server-to-server event.
     *
     * The implementation MUST authenticate the request (signature or equivalent) before
     * trusting a word of it, and must never take the amount or status from an
     * unverified body — a webhook endpoint is public and anyone may post to it.
     */
    public function handleWebhook(Request $request, PaymentMethod $method): PaymentResult;
}
