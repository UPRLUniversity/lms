<?php

namespace App\Http\Controllers\Commerce;

use App\Exceptions\CheckoutException;
use App\Exceptions\CouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\PlaceOrderRequest;
use App\Models\Order;
use App\Services\Commerce\CartService;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\OrderFulfilmentService;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Checkout — the point the guest journey requires an account, because an order has to
 * belong to somebody.
 *
 * Reading the checkout is open to a signed-out buyer: they see the order summary and
 * an inline sign-in panel (Section 13), which is a far better place to ask for an
 * account than a bare login form reached by a redirect that threw away the context.
 * Everything that WRITES — placing the order, the gateway callback — is auth-gated in
 * routes/web.php.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
        private readonly PaymentGatewayManager $gateways,
        private readonly OrderFulfilmentService $fulfilment,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        $cart = $this->carts->current($user);
        $pruned = $user ? $this->carts->pruneUnbuyable($cart, $user) : 0;
        $cart->refresh()->load('items.course.programmeParts.programme', 'items.course.media');

        if ($cart->items->isEmpty()) {
            // Say WHY it emptied. "Your cart is empty" after a course was silently
            // dropped for already being owned reads like the site lost the basket.
            return redirect()->route('cart.index')->with(
                'error',
                $pruned > 0
                    ? 'You already have access to everything that was in your cart, so there is nothing to pay for.'
                    : 'Your cart is empty.',
            );
        }

        $totals = $this->checkout->quote($cart, $user, CartController::couponCode($request));

        if ($user === null) {
            // Show the buyer what they are about to pay for and ask them to sign in on
            // the same screen. Remembering the intended URL means "log in" lands them
            // back here rather than on the dashboard, and MergeGuestCart carries the
            // basket across on the way.
            $request->session()->put('url.intended', route('checkout.show'));

            return view('commerce.checkout-guest', [
                'cart' => $cart,
                'totals' => $totals,
            ]);
        }

        return view('commerce.checkout', [
            'cart' => $cart,
            'totals' => $totals,
            'methods' => $this->gateways->available(),
            'user' => $user,
        ]);
    }

    public function store(PlaceOrderRequest $request): RedirectResponse
    {
        $cart = $this->carts->current($request->user());

        try {
            $method = $this->gateways->resolveChoice($request->validated('payment_method'));

            $order = $this->checkout->place(
                cart: $cart,
                user: $request->user(),
                paymentMethodKey: $method->key,
                billing: $request->billing(),
                couponCode: CartController::couponCode($request),
            );
        } catch (CheckoutException|CouponException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        // The coupon is now recorded on the order; keeping it in the session would
        // silently re-apply it to the next purchase.
        CartController::forgetCoupon($request);

        // A zero total (fully-discounted, or an all-free cart) still becomes a real
        // paid order so the receipt and enrolments exist — it just skips the gateway.
        if ((float) $order->total <= 0) {
            $this->fulfilment->markPaid($order, 'no-payment-required');

            return redirect()->route('orders.show', $order)->with('status', 'You are all set — enjoy the course.');
        }

        $intent = $this->gateways->driver($method)->initiate($order, $method);

        if ($intent->isRedirect()) {
            $order->update(['gateway_reference' => $intent->reference]);

            return redirect()->away($intent->redirectUrl);
        }

        if ($intent->isSettled()) {
            $this->fulfilment->markPaid($order, $intent->reference);

            return redirect()->route('orders.show', $order)->with('status', 'Payment complete — enjoy the course.');
        }

        // Offline / instruction: park it and show the buyer what to do next.
        $this->fulfilment->markAwaitingPayment($order);

        return redirect()->route('orders.show', $order)->with('status', $intent->message ?? 'Your order is awaiting payment.');
    }

    /**
     * The buyer's return from a hosted gateway.
     *
     * Verification always re-reads the truth from the provider using OUR order
     * reference — the query string is treated as a hint about which order to look at,
     * never as evidence of payment.
     */
    public function callback(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        if ($order->isPaid()) {
            return redirect()->route('orders.show', $order);
        }

        $method = $this->gateways->findByKey((string) $order->payment_method_key);

        if ($method === null || ! $method->hasDriver()) {
            return redirect()->route('orders.show', $order)
                ->with('error', 'We could not confirm that payment automatically. Our team will follow up.');
        }

        $result = $this->gateways->driver($method)->verify($order, $method, $request);

        if ($result->isPaid()) {
            $this->fulfilment->markPaid($order, $result->reference, $result->payload);

            return redirect()->route('orders.show', $order)->with('status', 'Payment confirmed — enjoy the course.');
        }

        if ($result->isFailed()) {
            $this->fulfilment->markFailed($order, $result->payload);

            return redirect()->route('orders.show', $order)
                ->with('error', $result->message ?? 'That payment did not go through.');
        }

        // Pending: deliberately NOT marked failed. The webhook may still confirm it,
        // and marking it failed would strand a buyer whose money did move.
        return redirect()->route('orders.show', $order)
            ->with('status', 'We are still confirming your payment. This page will show the result once it clears.');
    }
}
