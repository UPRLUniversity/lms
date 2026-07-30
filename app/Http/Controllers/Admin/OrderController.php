<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Commerce\OrderFulfilmentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Store → Orders. The institution's sales ledger, plus the two actions that only a
 * human can take: confirming that a bank transfer arrived, and recording a refund.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderFulfilmentService $fulfilment) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Order::class);

        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('q', ''));

        $orders = Order::query()
            ->with(['user', 'items'])
            ->when(in_array($status, OrderStatus::values(), true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Revenue counts paid orders only — pending and failed baskets are not money.
        $paidTotal = Order::query()->paid()->sum('total');

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'filters' => compact('status', 'search'),
            'paidTotal' => Money::format($paidTotal),
            'paidCount' => Order::query()->paid()->count(),
            'awaitingCount' => Order::query()->where('status', OrderStatus::AwaitingPayment->value)->count(),
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('view', $order);

        $order->load(['items.course', 'items.programme', 'user', 'coupon']);

        return view('admin.orders.show', ['order' => $order]);
    }

    /**
     * Confirm an offline payment. Goes through OrderFulfilmentService so the buyer is
     * enrolled, the coupon is redeemed and the receipt is sent — exactly as if a gateway
     * had confirmed it. markPaid is idempotent, so a double click is harmless.
     */
    public function markPaid(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        if ($order->isPaid()) {
            return back()->with('status', 'That order was already marked paid.');
        }

        $this->fulfilment->markPaid($order, 'manual:'.$request->user()->id, [
            'driver' => 'manual',
            'confirmed_by' => $request->user()->name,
            'confirmed_at' => now()->toIso8601String(),
        ]);

        return back()->with('status', 'Payment confirmed — the buyer now has access and has been notified.');
    }

    /**
     * Record a refund in our books.
     *
     * This does NOT call the gateway — money is moved back in the provider's dashboard
     * or the bank, by a human, and this is the record of that having happened.
     * Automating an outbound transfer is out of scope on purpose.
     */
    public function refund(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('manage', $order);

        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);

        $this->fulfilment->markRefunded($order, $data['note']);

        return back()->with('status', 'Refund recorded. Remember to return the money in your payment provider or bank.');
    }
}
