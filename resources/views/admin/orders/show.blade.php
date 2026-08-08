@php
    use App\Support\Money;
    use Illuminate\Support\Str;

    /** @var \App\Models\Order $order */
@endphp

<x-app-layout :title="'Order '.$order->shortReference()">
    <div class="mx-auto max-w-3xl space-y-6">
        <nav class="text-sm">
            <a href="{{ route('admin.orders.index') }}" class="inline-flex items-center gap-1.5 text-ink/65 hover:text-ink focus-ring rounded">
                <x-ui.icon name="arrow-left" class="h-4 w-4" /> Orders
            </a>
        </nav>

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-semibold text-ink">
                    <span class="font-mono">{{ $order->shortReference() }}</span>
                </h2>
                <p class="mt-1 text-sm text-ink/65">
                    Full reference <span class="font-mono">{{ $order->reference }}</span>
                </p>
            </div>
            <x-ui.badge :variant="$order->status->badge()">{{ $order->status->label() }}</x-ui.badge>
        </div>

        {{-- Buyer --}}
        <x-ui.card>
            <h3 class="font-display text-lg font-semibold text-ink">Buyer</h3>
            <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-ink/65">Name</dt>
                    <dd class="font-medium text-ink">{{ $order->user?->name ?? 'Deleted user' }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-ink/65">Email</dt>
                    <dd class="truncate font-medium text-ink">{{ $order->user?->email }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-ink/65">Placed</dt>
                    <dd class="font-medium text-ink">{{ $order->created_at->isoFormat('D MMM YYYY, HH:mm') }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-ink/65">Method</dt>
                    <dd class="font-medium text-ink">{{ $order->payment_method_key ? Str::headline($order->payment_method_key) : '—' }}</dd>
                </div>
                @if ($order->paid_at)
                    <div class="flex justify-between gap-3 sm:block">
                        <dt class="text-ink/65">Paid</dt>
                        <dd class="font-medium text-ink">{{ $order->paid_at->isoFormat('D MMM YYYY, HH:mm') }}</dd>
                    </div>
                @endif
                @if ($order->gateway_reference)
                    <div class="flex justify-between gap-3 sm:block">
                        <dt class="text-ink/65">Gateway reference</dt>
                        <dd class="truncate font-mono text-xs text-ink">{{ $order->gateway_reference }}</dd>
                    </div>
                @endif
            </dl>

            @if (filled($order->billing))
                <div class="mt-4 border-t border-line pt-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-ink/65">Billing details given</p>
                    <p class="mt-1 text-sm text-ink/75">
                        {{ collect($order->billing)->except('name')->filter()->implode(' · ') ?: '—' }}
                    </p>
                </div>
            @endif
        </x-ui.card>

        {{-- Items --}}
        <x-ui.card :padding="false">
            <h3 class="border-b border-line px-5 py-4 font-display text-lg font-semibold text-ink">Items</h3>
            <ul class="divide-y divide-line">
                @foreach ($order->items as $item)
                    <li class="flex items-start justify-between gap-3 px-5 py-3.5">
                        <div class="min-w-0">
                            <span class="text-ink">{{ $item->title }}</span>
                            @if ($item->kind->isEntryFee())
                                <span class="block text-xs text-ink/65">One-off programme fee</span>
                            @endif
                        </div>
                        <span class="shrink-0 font-medium text-ink">{{ $item->formattedLineTotal() }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="space-y-2 border-t border-line px-5 py-4 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-ink/65">Subtotal</dt>
                    <dd class="text-ink">{{ Money::format($order->subtotal) }}</dd>
                </div>
                @if ((float) $order->discount_total > 0)
                    <div class="flex justify-between gap-3 text-success">
                        <dt>Discount{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</dt>
                        <dd>−{{ Money::format($order->discount_total) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-3 border-t border-line pt-2">
                    <dt class="font-display font-semibold text-ink">Total</dt>
                    <dd class="font-display text-lg font-bold text-ink">{{ $order->formattedTotal() }}</dd>
                </div>
            </dl>
        </x-ui.card>

        {{-- Actions --}}
        @can('manage', $order)
            <x-ui.card>
                <h3 class="font-display text-lg font-semibold text-ink">Actions</h3>

                @if ($order->status->isOpen())
                    <div class="mt-3 rounded-xl border border-line bg-surface/60 p-4">
                        <p class="text-sm font-medium text-ink">Confirm payment</p>
                        <p class="mt-0.5 text-xs text-ink/65">
                            Use this once you have seen the money arrive. The buyer is enrolled, any code is
                            redeemed, and a receipt is sent — exactly as if a gateway had confirmed it.
                        </p>
                        <form method="POST" action="{{ route('admin.orders.mark-paid', $order) }}" class="mt-3"
                              x-data
                              @submit.prevent="if (await window.uprlConfirm({ title: 'Confirm this payment?', text: 'The buyer will be enrolled and notified.', confirmText: 'Yes, confirm' })) $el.submit()">
                            @csrf
                            <x-ui.button type="submit">Mark as paid</x-ui.button>
                        </form>
                    </div>
                @endif

                @if ($order->isPaid())
                    <div class="mt-3 rounded-xl border border-line p-4">
                        <p class="text-sm font-medium text-ink">Record a refund</p>
                        <p class="mt-0.5 text-xs text-ink/65">
                            This records the refund in our books. It does <strong class="font-medium">not</strong> move
                            money — return that in your payment provider or bank first.
                        </p>
                        <form method="POST" action="{{ route('admin.orders.refund', $order) }}" class="mt-3 space-y-3">
                            @csrf
                            <x-ui.field name="note" label="Why?" required
                                        placeholder="e.g. Student withdrew before the course began" />
                            <x-ui.button type="submit" variant="danger">Record refund</x-ui.button>
                        </form>
                    </div>
                @endif

                @if ($order->admin_note)
                    <p class="mt-3 rounded-xl bg-ink/5 px-4 py-3 text-sm text-ink/75">
                        <span class="font-medium text-ink">Note:</span> {{ $order->admin_note }}
                    </p>
                @endif
            </x-ui.card>
        @endcan
    </div>
</x-app-layout>
