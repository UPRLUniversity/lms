<?php

namespace App\Services\Payments\Drivers;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Payments\PaymentGateway;
use App\Support\Payments\PaymentIntent;
use App\Support\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * Offline payment. The buyer is shown the institution's account details and the order
 * reference to quote, and the order sits AwaitingPayment until an admin confirms the
 * money arrived (Store → Orders → Mark paid).
 *
 * There is deliberately no automatic confirmation here. Nobody but a human looking at
 * a bank statement can know a transfer landed, so this driver never returns `paid` —
 * only OrderFulfilmentService, driven by an admin action, can.
 */
class BankTransferGateway implements PaymentGateway
{
    public function initiate(Order $order, PaymentMethod $method): PaymentIntent
    {
        return PaymentIntent::instruct(
            'Transfer '.$order->formattedTotal().' and quote reference '.$order->shortReference().'.',
        );
    }

    /**
     * Nothing to verify — the buyer returning to the site tells us nothing about
     * whether their bank transfer went through.
     */
    public function verify(Order $order, PaymentMethod $method, Request $request): PaymentResult
    {
        return PaymentResult::pending($order->reference);
    }

    public function handleWebhook(Request $request, PaymentMethod $method): PaymentResult
    {
        return PaymentResult::ignored('Bank transfers are confirmed by an administrator, not by webhook.');
    }
}
