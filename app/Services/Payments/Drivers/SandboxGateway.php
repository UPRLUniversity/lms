<?php

namespace App\Services\Payments\Drivers;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Payments\PaymentGateway;
use App\Support\Payments\PaymentIntent;
use App\Support\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * A gateway that always succeeds, immediately, without leaving the app.
 *
 * This is what makes the whole commerce flow demonstrable and testable with no
 * merchant account, no keys and no network: a fresh `migrate:fresh --seed` can take a
 * course from catalogue to enrolment. It is also what the feature suite uses, so the
 * tests exercise the real CheckoutService and OrderFulfilmentService rather than
 * mocking them.
 *
 * It is enabled by default. Guarding against it reaching production is a deployment
 * concern, and the admin screen labels it "Sandbox (test only)" in the crimson Live
 * warning style precisely so nobody leaves it on by accident.
 */
class SandboxGateway implements PaymentGateway
{
    public function initiate(Order $order, PaymentMethod $method): PaymentIntent
    {
        return PaymentIntent::settled('sandbox_'.$order->reference);
    }

    public function verify(Order $order, PaymentMethod $method, Request $request): PaymentResult
    {
        return PaymentResult::paid(
            reference: 'sandbox_'.$order->reference,
            orderReference: $order->reference,
            payload: ['driver' => 'sandbox'],
        );
    }

    /**
     * The sandbox has no server to call it, but the endpoint exists for every driver,
     * so answer honestly rather than pretending a payment happened on an unauthenticated
     * public POST.
     */
    public function handleWebhook(Request $request, PaymentMethod $method): PaymentResult
    {
        return PaymentResult::ignored('The sandbox driver does not receive webhooks.');
    }
}
