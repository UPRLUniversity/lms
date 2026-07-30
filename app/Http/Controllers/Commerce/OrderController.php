<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The buyer's own purchase history and receipts.
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('commerce.orders.index', [
            'orders' => Order::query()
                ->forUser($request->user())
                ->with('items')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['items.course', 'items.programme', 'coupon']);

        // Offline methods carry the "how to pay" instructions an awaiting-payment
        // order still needs to display.
        $method = $order->payment_method_key
            ? PaymentMethod::where('key', $order->payment_method_key)->first()
            : null;

        return view('commerce.orders.show', [
            'order' => $order,
            'method' => $method,
        ]);
    }
}
